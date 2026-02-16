<?php
// Get full category tree
function getCategoryTree($parent = 0) {
    global $conn;

    $q = mysqli_query($conn,
        "SELECT * FROM categories WHERE parent='$parent' ORDER BY name ASC"
    );

    $cats = [];
    while ($c = mysqli_fetch_assoc($q)) {
        $c['children'] = getCategoryTree($c['id']);
        $cats[] = $c;
    }
    return $cats;
}

// Get all descendant category IDs (recursive)
function getCategoryDescendants($categoryId) {
    global $conn;

    $ids = [$categoryId];
    $q = mysqli_query($conn,
        "SELECT id FROM categories WHERE parent='$categoryId'"
    );

    while ($row = mysqli_fetch_assoc($q)) {
        $ids = array_merge($ids, getCategoryDescendants($row['id']));
    }

    return $ids;
}
// Get breadcrumb trail for a category (parent → child)
function getCategoryBreadcrumb($categoryId) {
    global $conn;

    $trail = [];

    while ($categoryId > 0) {
        $q = mysqli_query($conn,
            "SELECT id, name, slug, parent
             FROM categories
             WHERE id='$categoryId'
             LIMIT 1"
        );

        if (!$q || mysqli_num_rows($q) === 0) break;

        $cat = mysqli_fetch_assoc($q);
        array_unshift($trail, $cat);
        $categoryId = (int)$cat['parent'];
    }

    return $trail;
}
function getMenuCategories($parent = 0) {
    global $conn;

    $q = mysqli_query($conn,
        "SELECT * FROM categories
         WHERE parent='$parent' AND show_in_menu=1
         ORDER BY name ASC"
    );

    $cats = [];
    while ($c = mysqli_fetch_assoc($q)) {
        $c['children'] = getMenuCategories($c['id']);
        $cats[] = $c;
    }
    return $cats;
}