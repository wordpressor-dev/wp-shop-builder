<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductUpdateCandidateClassifier
{
    public static function label(
        ProductUpdateSnapshot $snapshot,
        ProductUpdateSuggestion $suggestion
    ): string {
        $envatoVersion = trim($suggestion->version);
        $currentVersion = trim($snapshot->version);

        if ($envatoVersion !== '') {
            return $envatoVersion === $currentVersion
                ? 'SAME VERSION'
                : 'REVIEW REQUIRED';
        }

        $productType = CatalogProductType::infer(
            $snapshot->baseTitle,
            $snapshot->salesPage
        );
        $envatoDate = trim($suggestion->updateDate);
        $currentDate = trim($snapshot->sourceUpdateDate);

        if (
            $productType === CatalogProductType::TEMPLATE_KIT
            && $currentVersion === ''
            && $envatoDate !== ''
            && $currentDate !== ''
            && $envatoDate <= $currentDate
        ) {
            return 'SAME SOURCE DATE';
        }

        return 'REVIEW REQUIRED';
    }
}
