<?php
/**
 * admin_helpers.php
 * Small helpers shared by the website admin pages (dashboard, reports, orders,
 * pricing estimates). Include with: require_once 'admin_helpers.php';
 *
 * Presentation / read-only helpers only. It does not start the session, check
 * roles or touch permissions: every page still does that itself.
 */

// "This month", "today" and every date() in the admin pages follow the shop's
// time. Keep the database time zone the same (set it in config/db.php, e.g.
// date_default_timezone_set('Asia/Manila') and SET time_zone = '+08:00').
date_default_timezone_set('Asia/Manila');

// ---------------------------------------------------------------------
// Safe queries: a failed query returns an empty result instead of a fatal
// error, and the real reason goes to the error log.
// ---------------------------------------------------------------------

/** Run a query. Returns the mysqli_result, or false when it failed. */
function wa_query(mysqli $db, string $sql)
{
    try {
        $result = $db->query($sql);
        if ($result === false) {
            error_log('wa_query failed: ' . $db->error);
        }
        return $result;
    } catch (Throwable $e) {
        error_log('wa_query failed: ' . $e->getMessage());
        return false;
    }
}

/** First row of a query as an array (empty array when the query fails or has no rows). */
function wa_fetch_one(mysqli $db, string $sql): array
{
    $result = wa_query($db, $sql);
    if ($result instanceof mysqli_result) {
        return $result->fetch_assoc() ?: [];
    }
    return [];
}

/** Every row of a query (empty array when the query fails). */
function wa_fetch_all(mysqli $db, string $sql): array
{
    $result = wa_query($db, $sql);
    if ($result instanceof mysqli_result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

// ---------------------------------------------------------------------
// Files: only ever link to files that really sit inside the allowed folder.
// ---------------------------------------------------------------------

/**
 * Resolve $file inside $baseDir. Returns the path relative to $baseDir (with
 * forward slashes) when the file exists AND is inside $baseDir, otherwise null.
 * "../" tricks, absolute paths and symlinks that leave the folder all return null.
 */
function wa_resolve_file(string $baseDir, string $file): ?string
{
    $file = str_replace('\\', '/', $file);
    if ($file === '' || strpos($file, "\0") !== false) {
        return null;
    }
    $base = realpath($baseDir);
    if ($base === false) {
        return null;
    }
    $real = realpath($base . '/' . ltrim($file, '/'));
    if ($real === false || !is_file($real)) {
        return null;
    }
    $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($real, $prefix) !== 0) {
        return null;
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($prefix)));
}

/**
 * One label/value tile in an item's customization grid.
 */
function cust_row(string $label, $value, bool $wide = false): void
{
    echo '<div class="cust-item' . ($wide ? ' cust-item--wide' : '') . '"><span class="cust-label">'
        . htmlspecialchars($label) . '</span><span class="cust-value">'
        . htmlspecialchars((string) $value) . '</span></div>';
}

/**
 * Normalizes the "original design file(s)" value for one side into a plain
 * list of path strings. Accepts the new array shape (front_uploaded_files /
 * back_uploaded_files) as well as the old single-string shape still sitting in
 * older orders. Anything else becomes an empty list.
 */
function normalize_design_file_list($value): array
{
    if (is_array($value)) {
        return array_values(array_filter($value, static function ($item) {
            return is_string($item) && $item !== '';
        }));
    }
    if (is_string($value) && $value !== '') {
        return [$value];
    }
    return [];
}

function is_pdf_file(?string $name): bool
{
    return $name !== null && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf';
}

/**
 * First-page preview of a PDF, shown where an image thumbnail would be. The <object> ignores the mouse so a click
 * still goes to the surrounding link; browsers that can't draw PDFs inline fall back to a labelled PDF icon.
 */
function pdf_thumb_html(string $url, string $extra_class = ''): string
{
    $src = $url . (strpos($url, '#') === false ? '#page=1&toolbar=0&navpanes=0&scrollbar=0&view=FitH' : '');
    return '<span class="pdf-thumb ' . htmlspecialchars($extra_class) . '"><object data="' . htmlspecialchars($src)
        . '" type="application/pdf" tabindex="-1" aria-hidden="true"><span class="pdf-thumb-fallback">'
        . '<i class="fas fa-file-pdf" aria-hidden="true"></i>PDF</span></object></span>';
}

/**
 * Render one design-file tile (thumbnail opens the file, a link downloads it),
 * or a "file not found" tile. $accentVar is kept for call compatibility:
 * 'var(--primary)' marks a generated mockup, anything else an original upload.
 * The file must be inside assets/uploads, otherwise it is shown as not found.
 */
function render_design_preview(string $file, string $label, string $accentVar = ''): void
{
    $rel = wa_resolve_file('../../assets/uploads', $file);
    $exists = $rel !== null;
    $path = '../../assets/uploads/' . ($exists ? $rel : '');
    $mock = $accentVar === 'var(--primary)';
    $cls = 'file-tile' . ($mock ? ' file-tile--mock' : '') . ($exists ? '' : ' file-tile--missing');

    echo '<div class="' . $cls . '">';
    if ($exists) {
        echo '<a class="file-tile-thumb" href="' . htmlspecialchars($path) . '" target="_blank" rel="noopener" title="Open ' . htmlspecialchars($label) . '">';
        echo is_pdf_file($file)
            ? pdf_thumb_html($path)
            : '<img src="' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($label) . '" loading="lazy">';
        echo '</a>';
        echo '<span class="file-tile-label">' . htmlspecialchars($label) . '</span>';
        echo '<a class="file-tile-meta" href="' . htmlspecialchars($path) . '" download="' . htmlspecialchars(basename($file)) . '"><i class="fas fa-download" aria-hidden="true"></i> Download</a>';
    } else {
        echo '<span class="file-tile-thumb"><i class="fas fa-file-circle-exclamation" aria-hidden="true"></i></span>';
        echo '<span class="file-tile-label">' . htmlspecialchars($label) . '</span>';
        echo '<span class="file-tile-meta">File not found: ' . htmlspecialchars(basename($file)) . '</span>';
    }
    echo '</div>';
}