<?php
$directory = '/volume3/Client_Preview'; // Path to your folder
$snapshot_file = '/volume3/webhook/snapshot.json';

// Load snapshot of previous state
$snapshot = file_exists($snapshot_file) ? json_decode(file_get_contents($snapshot_file), true) : [];

// Scan current state of the directory
$current = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
    if ($file->isFile()) {
        $current[$file->getPathname()] = md5_file($file->getPathname());
    }
}

echo json_encode($current);

// Detect changes
$added = array_diff_key($current, $snapshot);
$removed = array_diff_key($snapshot, $current);
$modified = array_diff_assoc(array_intersect_key($current, $snapshot), $snapshot);

// Output changes
echo "Added:\n" . print_r($added, true);
echo "Removed:\n" . print_r($removed, true);
echo "Modified:\n" . print_r($modified, true);

// Save the current state as a snapshot
file_put_contents($snapshot_file, json_encode($current));
?>
