<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\DataLoader\Util;

use Klipper\Component\DoctrineExtensionsExtra\Model\BaseTranslation;
use Klipper\Contracts\Model\TranslatableInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class DataLoaderTranslationUtil
{
    public static function getId(string $locale, string $field): string
    {
        return $locale.':'.$field;
    }

    public static function getIdByTranslation(BaseTranslation $translation): string
    {
        return static::getId($translation->getLocale(), $translation->getField());
    }

    /**
     * @return BaseTranslation[]
     */
    public static function getTranslationsMap(TranslatableInterface $entity): array
    {
        /** @var BaseTranslation[] $translations */
        $translations = [];

        foreach ($entity->getTranslations() as $translation) {
            /* @var BaseTranslation $translation */
            $translations[static::getIdByTranslation($translation)] = $translation;
        }

        return $translations;
    }

    /**
     * @param string            $translationEntityClass The class name of the entity translation class
     * @param array[]           $itemTranslations
     * @param BaseTranslation[] $translations
     *
     * @return bool Check if the entity is edited or not
     */
    public static function injectTranslations(
        object $entity,
        string $translationEntityClass,
        array $itemTranslations,
        array $translations
    ): bool {
        $edited = false;

        foreach ($itemTranslations as $transLocale => $transItem) {
            foreach ($transItem as $transField => $transValue) {
                $id = static::getId($transLocale, $transField);

                if (!isset($translations[$id])) {
                    $edited = true;

                    /** @var BaseTranslation $transEntity */
                    $transEntity = new $translationEntityClass($transLocale, $transField, $transValue);
                    $transEntity->setObject($entity);
                    $entity->getTranslations()->add($transEntity);
                    $availables = $entity->getAvailableLocales();
                    $availables[] = $transLocale;
                    $availables = array_unique($availables);
                    sort($availables);
                    $entity->setAvailableLocales($availables);
                } elseif ($translations[$id]->getContent() !== $transValue) {
                    $edited = true;
                    $translations[$id]->setContent($transValue);
                }
            }
        }

        return $edited;
    }
}
