<?php
/**
 * includes/reports.php
 * Shared report helpers used by both admin/reports.php and
 * actions/export_report.php.
 *
 * All report types are read-only and operate on apprehension_records,
 * traffic_violators and vehicles. No new schema is required.
 */

/**
 * Catalogue of available reports.
 * Each entry declares label, description, whether it uses the date range,
 * and the column headers used for print and CSV output.
 */
function report_types(): array
{
    return [
        'apprehensions' => [
            'label'       => 'Apprehension records',
            'description' => 'Individual apprehension records with violator, vehicle, category and location.',
            'uses_range'  => true,
            'columns'     => [
                'Reference', 'Date', 'Time', 'Violator Code', 'Violator',
                'Plate', 'Category', 'Location', 'Latitude', 'Longitude',
                'Recorded By', 'Status',
            ],
        ],
        'violators' => [
            'label'       => 'Traffic violators',
            'description' => 'Active violators with their vehicle and apprehension counts.',
            'uses_range'  => false,
            'columns'     => [
                'Violator Code', 'First Name', 'Middle Name', 'Last Name', 'Suffix',
                'Address', 'Contact', 'Vehicles', 'Apprehensions', 'Status', 'Created',
            ],
        ],
        'vehicles' => [
            'label'       => 'Registered vehicles',
            'description' => 'All vehicles with plate number, type, make/model and assigned violator.',
            'uses_range'  => false,
            'columns'     => [
                'Plate', 'Type', 'Make', 'Model', 'Color',
                'Violator Code', 'Violator', 'Status', 'Created',
            ],
        ],
        'repeat_incidents' => [
            'label'       => 'Repeat incident summary',
            'description' => 'Violators with two or more recorded apprehensions in the selected range.',
            'uses_range'  => true,
            'columns'     => [
                'Violator Code', 'Violator', 'Recorded Incidents',
                'First Recorded', 'Last Recorded', 'Categories',
            ],
        ],
        'location_summary' => [
            'label'       => 'Location summary',
            'description' => 'Apprehension counts grouped by location text, ranked highest first.',
            'uses_range'  => true,
            'columns'     => ['Location', 'Apprehensions', 'Distinct Violators'],
        ],
    ];
}

/**
 * Build a report's data for the given filters.
 *
 * Returns:
 *   ['title' => string, 'columns' => string[], 'rows' => array[], 'row_count' => int]
 *
 * Filters:
 *   date_from   Y-m-d or '' (ignored for types that don't use_range)
 *   date_to     Y-m-d or ''
 *   category_id int > 0 or 0 for all
 */
function build_report_data(string $type, array $filters): array
{
    $types = report_types();
    if (!isset($types[$type])) {
        throw new InvalidArgumentException('Unknown report type.');
    }

    $meta       = $types[$type];
    $dateFrom   = $filters['date_from'] ?? '';
    $dateTo     = $filters['date_to']   ?? '';
    $categoryId = (int) ($filters['category_id'] ?? 0);

    if (!$meta['uses_range']) {
        $dateFrom = '';
        $dateTo   = '';
    }

    /* ============================================================== */
    /* Apprehensions                                                  */
    /* ============================================================== */
    if ($type === 'apprehensions') {
        $where  = ["a.record_status = 'active'"];
        $params = [];

        if ($dateFrom !== '') {
            $where[] = 'a.apprehension_date >= :date_from';
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'a.apprehension_date <= :date_to';
            $params[':date_to'] = $dateTo;
        }
        if ($categoryId > 0) {
            $where[] = 'a.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $rows = db_all(
            "SELECT a.reference_no, a.apprehension_date, a.apprehension_time,
                    a.location_text, a.latitude, a.longitude, a.record_status,
                    tv.violator_code,
                    CONCAT(tv.first_name, ' ', tv.last_name) AS violator_name,
                    veh.plate_number,
                    vc.violation_name,
                    CONCAT(ad.full_name, ' (', ad.username, ')') AS recorded_by
               FROM apprehension_records a
         INNER JOIN traffic_violators tv  ON tv.violator_id  = a.violator_id
         INNER JOIN violation_categories vc ON vc.category_id = a.category_id
          LEFT JOIN vehicles veh ON veh.vehicle_id = a.vehicle_id
          LEFT JOIN administrators ad ON ad.admin_id = a.recorded_by
              WHERE " . implode(' AND ', $where) . "
           ORDER BY a.apprehension_date DESC, a.apprehension_id DESC",
            $params
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                $r['reference_no'],
                $r['apprehension_date'],
                $r['apprehension_time'] ?? '',
                $r['violator_code'],
                $r['violator_name'],
                $r['plate_number'] ?? '',
                $r['violation_name'],
                $r['location_text'],
                $r['latitude']  !== null ? (string) $r['latitude']  : '',
                $r['longitude'] !== null ? (string) $r['longitude'] : '',
                $r['recorded_by'] ?? '',
                $r['record_status'],
            ];
        }

        return [
            'title'     => 'Apprehension Records',
            'columns'   => $meta['columns'],
            'rows'      => $out,
            'row_count' => count($out),
        ];
    }

    /* ============================================================== */
    /* Violators                                                      */
    /* ============================================================== */
    if ($type === 'violators') {
        $rows = db_all(
            "SELECT v.violator_code, v.first_name, v.middle_name, v.last_name, v.suffix,
                    v.address, v.contact_number, v.record_status, v.created_at,
                    (SELECT COUNT(*) FROM vehicles veh
                      WHERE veh.violator_id = v.violator_id AND veh.record_status = 'active') AS vehicle_count,
                    (SELECT COUNT(*) FROM apprehension_records a
                      WHERE a.violator_id = v.violator_id AND a.record_status = 'active') AS apprehension_count
               FROM traffic_violators v
              WHERE v.record_status = 'active'
           ORDER BY v.last_name ASC, v.first_name ASC"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                $r['violator_code'],
                $r['first_name'],
                $r['middle_name'] ?? '',
                $r['last_name'],
                $r['suffix'] ?? '',
                $r['address'],
                $r['contact_number'] ?? '',
                (string) $r['vehicle_count'],
                (string) $r['apprehension_count'],
                $r['record_status'],
                $r['created_at'],
            ];
        }

        return [
            'title'     => 'Traffic Violators',
            'columns'   => $meta['columns'],
            'rows'      => $out,
            'row_count' => count($out),
        ];
    }

    /* ============================================================== */
    /* Vehicles                                                       */
    /* ============================================================== */
    if ($type === 'vehicles') {
        $rows = db_all(
            "SELECT v.plate_number, v.vehicle_type, v.vehicle_make, v.vehicle_model,
                    v.vehicle_color, v.record_status, v.created_at,
                    tv.violator_code,
                    CONCAT(tv.first_name, ' ', tv.last_name) AS violator_name
               FROM vehicles v
          LEFT JOIN traffic_violators tv ON tv.violator_id = v.violator_id
              WHERE v.record_status = 'active'
           ORDER BY v.plate_number ASC"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                $r['plate_number'],
                $r['vehicle_type']  ?? '',
                $r['vehicle_make']  ?? '',
                $r['vehicle_model'] ?? '',
                $r['vehicle_color'] ?? '',
                $r['violator_code'] ?? '',
                $r['violator_name'] ?? '',
                $r['record_status'],
                $r['created_at'],
            ];
        }

        return [
            'title'     => 'Registered Vehicles',
            'columns'   => $meta['columns'],
            'rows'      => $out,
            'row_count' => count($out),
        ];
    }

    /* ============================================================== */
    /* Repeat incidents                                               */
    /* ============================================================== */
    if ($type === 'repeat_incidents') {
        $where  = ["a.record_status = 'active'"];
        $params = [];

        if ($dateFrom !== '') {
            $where[] = 'a.apprehension_date >= :date_from';
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'a.apprehension_date <= :date_to';
            $params[':date_to'] = $dateTo;
        }
        if ($categoryId > 0) {
            $where[] = 'a.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $rows = db_all(
            "SELECT tv.violator_code,
                    CONCAT(tv.first_name, ' ', tv.last_name) AS violator_name,
                    COUNT(a.apprehension_id) AS total,
                    MIN(a.apprehension_date) AS first_date,
                    MAX(a.apprehension_date) AS last_date,
                    GROUP_CONCAT(DISTINCT vc.violation_name
                                 ORDER BY vc.violation_name SEPARATOR ', ') AS categories
               FROM apprehension_records a
         INNER JOIN traffic_violators tv   ON tv.violator_id = a.violator_id
         INNER JOIN violation_categories vc ON vc.category_id = a.category_id
              WHERE " . implode(' AND ', $where) . "
           GROUP BY tv.violator_id, tv.violator_code, tv.first_name, tv.last_name
             HAVING total >= 2
           ORDER BY total DESC, tv.last_name ASC, tv.first_name ASC",
            $params
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                $r['violator_code'],
                $r['violator_name'],
                (string) $r['total'],
                $r['first_date'],
                $r['last_date'],
                $r['categories'],
            ];
        }

        return [
            'title'     => 'Repeat Incident Summary',
            'columns'   => $meta['columns'],
            'rows'      => $out,
            'row_count' => count($out),
        ];
    }

    /* ============================================================== */
    /* Location summary                                               */
    /* ============================================================== */
    if ($type === 'location_summary') {
        $where  = ["a.record_status = 'active'"];
        $params = [];

        if ($dateFrom !== '') {
            $where[] = 'a.apprehension_date >= :date_from';
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'a.apprehension_date <= :date_to';
            $params[':date_to'] = $dateTo;
        }
        if ($categoryId > 0) {
            $where[] = 'a.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $rows = db_all(
            "SELECT a.location_text, COUNT(*) AS total,
                    COUNT(DISTINCT a.violator_id) AS distinct_violators
               FROM apprehension_records a
              WHERE " . implode(' AND ', $where) . "
           GROUP BY a.location_text
           ORDER BY total DESC, a.location_text ASC",
            $params
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                $r['location_text'],
                (string) $r['total'],
                (string) $r['distinct_violators'],
            ];
        }

        return [
            'title'     => 'Location Summary',
            'columns'   => $meta['columns'],
            'rows'      => $out,
            'row_count' => count($out),
        ];
    }

    throw new InvalidArgumentException('Unhandled report type: ' . $type);
}

/**
 * Escape a cell so Excel doesn't interpret it as a formula.
 * Prevents CSV injection (=, +, -, @, TAB, CR leading characters).
 */
function csv_safe(string $value): string
{
    if ($value === '') {
        return '';
    }
    $first = $value[0];
    if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Write a UTF-8 CSV file (with BOM for Excel) to $path.
 */
function write_csv(string $path, array $columns, array $rows): void
{
    $handle = fopen($path, 'w');
    if ($handle === false) {
        throw new RuntimeException('Unable to create export file.');
    }

    // UTF-8 BOM — Excel opens otherwise-corrupted characters correctly.
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $columns);

    foreach ($rows as $row) {
        fputcsv($handle, array_map('csv_safe', $row));
    }

    fclose($handle);
    @chmod($path, 0640);
}

/**
 * Purge export files older than $maxAgeSeconds.
 * Called at export time so cleanup happens automatically.
 */
function cleanup_old_exports(string $dir, int $maxAgeSeconds = 3600): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach ((array) glob(rtrim($dir, '/') . '/*') as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $maxAgeSeconds) {
            @unlink($file);
        }
    }
}