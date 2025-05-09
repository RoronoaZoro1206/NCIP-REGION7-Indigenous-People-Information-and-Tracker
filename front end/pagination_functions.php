<?php

/**
 * Renders the pagination links based on current page
 * 
 * @param int $current_page The current page number
 * @param int $total_pages Total number of pages
 * @return string HTML for pagination
 */
function renderPagination($current_page, $total_pages = 5) {
    $html = '<div class="general-pagination group">';
    
    // Previous page link
    if ($current_page > 1) {
        $prev_page = $current_page - 1;
        $prev_url = ($prev_page == 1) ? 'announcement.php' : "announcement{$prev_page}.php";
        $html .= "<a href='{$prev_url}'>&lsaquo;</a>"; // Left-pointing single angle quotation mark
    }
    
    // Generate page links
    for ($i = 1; $i <= $total_pages; $i++) {
        $page_url = ($i == 1) ? 'announcement.php' : "announcement{$i}.php";
        $selected = ($current_page == $i) ? ' class="selected"' : '';
        
        $html .= "<a href='{$page_url}'{$selected}>{$i}</a>";
    }
    
    // Next page link
    if ($current_page < $total_pages) {
        $next_page = $current_page + 1;
        $next_url = ($next_page == 1) ? 'announcement.php' : "announcement{$next_page}.php";
        $html .= "<a href='{$next_url}'>&rsaquo;</a>"; // Right-pointing single angle quotation mark
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Gets the base filename of the current page without extension
 * 
 * @return string Current page filename
 */
function getCurrentPageName() {
    $path_parts = pathinfo($_SERVER['SCRIPT_NAME']);
    return $path_parts['filename'];
}

/**
 * Determines the current page number based on filename
 * 
 * @return int Current page number
 */
function getCurrentPageNumber() {
    $current_page = getCurrentPageName();
    
    if ($current_page == 'announcement') {
        return 1;
    } else {
        // Extract page number from filenames like 'announcement2'
        return (int)str_replace('announcement', '', $current_page);
    }
}