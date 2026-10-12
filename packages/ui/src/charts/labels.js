export function categoryLabel(category, t) {
    if (category.key === 'other') {
        return t('charts.other');
    }

    if (category.key === 'uncategorized') {
        return t('charts.uncategorized');
    }

    return category.name;
}
