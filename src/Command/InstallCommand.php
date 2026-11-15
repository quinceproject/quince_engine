<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Command;

use Context;
use Db;
use Employee;
use Module;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeManagerBuilder;
use PrestaShop\PrestaShop\Core\Context\ContextBuilderPreparer;
use PrestaShop\PrestaShop\Core\Module\ModuleManager;
use Shop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * `quince:install`: instaluje albo aktualizuje w sklepie wszystkie moduły Quince,
 * których pliki są w `modules/`. Z `--enable-theme` włącza też motyw Quince we
 * wszystkich sklepach; bez niej aktywny motyw zostaje, jaki był.
 *
 * Pliki kładzie tam Composer (pakiet `quinceproject/setup`) albo ktokolwiek inny;
 * polecenie niczego nie pobiera. Kolejność wynika z zależności zadeklarowanych
 * przez moduły, więc moduł instaluje się po tych, których wymaga.
 *
 * Konsola PrestaShopa działa w kontekście jednego sklepu. Przełączamy ją na
 * wszystkie sklepy, żeby moduł był włączony i podpięty pod haki w każdym z nich,
 * tak jak przy instalacji z panelu w kontekście „Wszystkie sklepy”.
 */
final class InstallCommand extends Command
{
    private const THEME = 'quince';

    public function __construct(
        private readonly ModuleManager $modules,
        private readonly ContextBuilderPreparer $contextPreparer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('quince:install')
            ->setDescription('Installs or updates every Quince module found in modules/. With --enable-theme also makes Quince the active theme in every shop.')
            ->addOption('enable-theme', null, InputOption::VALUE_NONE, 'Make Quince the active theme in every shop.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Bez pracownika haki modułów nie działają (LegacyHookSubscriber), a bez
        // języka repozytorium modułów nie zbuduje klucza pamięci podręcznej.
        // Tak samo robi rdzeń w `prestashop:module`.
        $context = Context::getContext();
        if (!$context->employee) {
            $context->employee = new Employee(42);
        }
        $this->contextPreparer->prepareLanguageId((int) \Configuration::get('PS_LANG_DEFAULT'));

        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $failed = false;
        $rows = [];

        foreach ($this->installOrder($this->moduleNames()) as $name) {
            try {
                $rows[] = [$name, $this->installOrUpgrade($name)];
            } catch (\Throwable $e) {
                $rows[] = [$name, 'failed: ' . $e->getMessage()];
                $failed = true;
            }
        }

        $io->table(['Module', 'Result'], $rows);

        if (is_dir(_PS_ALL_THEMES_DIR_ . self::THEME)) {
            $this->refreshTheme();

            if ($input->getOption('enable-theme')) {
                try {
                    $shops = $this->enableTheme();
                    $io->text(sprintf('Quince theme enabled in %d shop(s).', $shops));
                } catch (\Throwable $e) {
                    $io->error('The Quince theme could not be enabled: ' . $e->getMessage());
                    $failed = true;
                }
            }
        }

        if ($failed) {
            $io->error('Some steps failed. The rest is installed; run the command again after fixing the cause.');

            return Command::FAILURE;
        }

        $io->success('Quince is installed.');

        return Command::SUCCESS;
    }

    /**
     * Moduły Quince obecne na dysku: katalog `quince_*` z plikiem głównym modułu.
     *
     * @return list<string>
     */
    private function moduleNames(): array
    {
        $names = [];

        foreach (glob(_PS_MODULE_DIR_ . 'quince_*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (is_file($dir . '/' . $name . '.php')) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Kolejność, w której każdy moduł przychodzi po swoich zależnościach.
     * Zależności spoza listy (moduły rdzenia) pomijamy: sprawdzi je instalator.
     *
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function installOrder(array $names): array
    {
        $requires = [];
        foreach ($names as $name) {
            $module = Module::getInstanceByName($name);
            $deps = $module instanceof Module ? (array) $module->dependencies : [];
            $requires[$name] = array_values(array_intersect($deps, $names));
        }

        $order = [];
        $visit = static function (string $name, array $path) use (&$visit, &$order, $requires): void {
            if (in_array($name, $order, true) || in_array($name, $path, true)) {
                return;
            }
            foreach ($requires[$name] as $dep) {
                $visit($dep, [...$path, $name]);
            }
            $order[] = $name;
        };

        // Silnik pierwszy, nawet gdy żaden moduł go nie deklaruje.
        if (in_array('quince_engine', $names, true)) {
            $visit('quince_engine', []);
        }
        foreach ($names as $name) {
            $visit($name, []);
        }

        return $order;
    }

    private function installOrUpgrade(string $name): string
    {
        $module = Module::getInstanceByName($name);
        if (!$module instanceof Module) {
            throw new \RuntimeException('the module class could not be loaded');
        }

        if (!Module::isInstalled($name)) {
            if (!$this->modules->install($name)) {
                throw new \RuntimeException($this->modules->getError($name) ?: 'PrestaShop could not install it');
            }

            return 'installed ' . $module->version;
        }

        $current = (string) Db::getInstance()->getValue(
            'SELECT version FROM `' . _DB_PREFIX_ . 'module` WHERE name = \'' . pSQL($name) . '\''
        );

        if (version_compare($module->version, $current, '<=')) {
            return 'up to date ' . $current;
        }

        if (!$this->modules->upgrade($name)) {
            throw new \RuntimeException($this->modules->getError($name) ?: 'PrestaShop could not update it');
        }

        return 'updated ' . $current . ' → ' . $module->version;
    }

    /**
     * Pliki motywu mogły się właśnie zmienić. Rdzeń trzyma ustawienia z theme.yml
     * w osobnym pliku na sklep i nie czyta theme.yml, dopóki ten plik istnieje,
     * a skompilowane CSS i JS motywu leżą w assets/cache. Aktywnego motywu nie
     * zmieniamy.
     */
    private function refreshTheme(): void
    {
        $fs = new Filesystem();
        $fs->remove(glob(_PS_CONFIG_DIR_ . 'themes/' . self::THEME . '/shop*.json') ?: []);
        $fs->remove(glob(_PS_ALL_THEMES_DIR_ . self::THEME . '/assets/cache/*') ?: []);
    }

    /**
     * Włącza motyw Quince w każdym sklepie. Menedżer motywów działa na sklepie
     * z kontekstu, więc budujemy go osobno dla każdego sklepu.
     *
     * @return int liczba sklepów
     */
    private function enableTheme(): int
    {
        $context = Context::getContext();
        $original = $context->shop;
        $count = 0;

        try {
            foreach (Shop::getShops(false, null, true) as $idShop) {
                $shop = new Shop((int) $idShop);
                Shop::setContext(Shop::CONTEXT_SHOP, (int) $idShop);
                $context->shop = $shop;

                $manager = (new ThemeManagerBuilder($context, Db::getInstance()))->build();
                if (!$manager->enable(self::THEME, true)) {
                    throw new \RuntimeException(sprintf('shop %d rejected the theme', $idShop));
                }
                ++$count;
            }
        } finally {
            $context->shop = $original;
            if (Shop::isFeatureActive()) {
                Shop::setContext(Shop::CONTEXT_ALL);
            }
        }

        return $count;
    }
}
