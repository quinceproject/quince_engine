<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Catalog;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;
use Image;
use ImageManager;
use ImageType;
use PrestaShopLogger;

/**
 * Brakujące formaty miniatur zdjęcia produktu (WebP, AVIF, PNG).
 *
 * Webservice przy `POST /api/images/products/{id}` tworzy miniatury tylko
 * w formacie zdjęcia (`$image->image_format`, czyli jpg), bez formatów
 * z `PS_IMAGE_FORMAT` (`WebserviceSpecificManagementImages`, PS 9.2).
 * Regeneracja z panelu je tworzy, więc zdjęcia wgrane przez API po ostatniej
 * regeneracji nie miały plików `.webp`, a sklep dorabiał je dopiero przy
 * wyświetlaniu strony.
 *
 * Klasa robi po wgraniu to samo, co regeneracja z panelu
 * (`ImageThumbnailsRegenerator`): ten sam zestaw formatów co
 * `ImageFormatConfiguration::getGenerationFormats()`, to samo
 * `ImageManager::resize()` z oryginału. Tworzy tylko pliki, których nie ma;
 * niczego nie nadpisuje ani nie usuwa.
 *
 * Wywoływana z hooka `actionWatermark`, który rdzeń wysyła po każdym
 * wgraniu zdjęcia produktu: Webservice, panel, import CSV. Błąd jednego
 * pliku trafia do dziennika sklepu i nie przerywa wgrywania.
 */
final class ThumbnailFormats
{
    /** Formaty, które rdzeń umie tworzyć (`ImageFormatConfiguration::SUPPORTED_FORMATS`). */
    private const SUPPORTED = ['jpg', 'png', 'webp', 'avif'];

    /** Funkcja GD potrzebna do zapisu formatu; bez niej format pomijamy po cichu. */
    private const GD_WRITERS = ['png' => 'imagepng', 'webp' => 'imagewebp', 'avif' => 'imageavif'];

    /**
     * Uzupełnia miniatury jednego zdjęcia.
     *
     * @return int liczba utworzonych plików
     */
    public function complete(int $idImage): int
    {
        if ($idImage <= 0) {
            return 0;
        }

        $image = new Image($idImage);

        if (!$image->id) {
            return 0;
        }

        $sciezka = _PS_PRODUCT_IMG_DIR_ . $image->getExistingImgPath();
        $oryginal = $sciezka . '.jpg';

        if (!is_file($oryginal) || !filesize($oryginal)) {
            return 0;
        }

        $utworzone = 0;

        foreach (ImageType::getImagesTypes('products') as $typ) {
            foreach ($this->formats() as $format) {
                $cel = $sciezka . '-' . stripslashes((string) $typ['name']) . '.' . $format;

                if (is_file($cel)) {
                    continue;
                }

                if ($this->resize($oryginal, $cel, $typ, $format)) {
                    ++$utworzone;
                } else {
                    PrestaShopLogger::addLog(
                        sprintf('Quince: could not create thumbnail %s for image %d', basename($cel), $idImage),
                        2,
                        null,
                        'Image',
                        $idImage,
                        true
                    );
                }
            }
        }

        return $utworzone;
    }

    /**
     * Formaty do utworzenia: zawsze jpg, potem włączone w `PS_IMAGE_FORMAT`,
     * ta sama kolejność i te same zasady co w rdzeniu. Format bez funkcji
     * zapisu w GD odpada, żeby dziennik nie zapełniał się tym samym błędem
     * przy każdym zdjęciu.
     *
     * @return string[]
     */
    private function formats(): array
    {
        $formaty = ['jpg'];

        foreach (explode(',', (string) Configuration::get('PS_IMAGE_FORMAT')) as $format) {
            $format = trim($format);

            if (!in_array($format, self::SUPPORTED, true) || in_array($format, $formaty, true)) {
                continue;
            }

            if (isset(self::GD_WRITERS[$format]) && !function_exists(self::GD_WRITERS[$format])) {
                continue;
            }

            $formaty[] = $format;
        }

        return $formaty;
    }

    /** @param array<string,mixed> $typ wiersz z `ImageType::getImagesTypes()` */
    private function resize(string $oryginal, string $cel, array $typ, string $format): bool
    {
        try {
            $blad = 0;
            $szerokoscCelu = null;
            $wysokoscCelu = null;
            $szerokoscZrodla = null;
            $wysokoscZrodla = null;

            return (bool) ImageManager::resize(
                $oryginal,
                $cel,
                (int) $typ['width'],
                (int) $typ['height'],
                $format,
                false,
                $blad,
                $szerokoscCelu,
                $wysokoscCelu,
                5,
                $szerokoscZrodla,
                $wysokoscZrodla,
                (string) ($typ['image_fitment'] ?? 'fit')
            );
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('Quince: thumbnail error: ' . $e->getMessage(), 3, null, 'Image', 0, true);

            return false;
        }
    }
}
