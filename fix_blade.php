<?php
$path = 'c:\Users\lenovo\Herd\netpassv3\resources\views\superadmin\routers.blade.php';
$content = file_get_contents($path);

// Replace the @php block to add $confirmMsg
$oldPhp = "@php\n                                \$location = \$r->location;\n                                \$underMaintenance = (bool) (\$location->maintenance_mode ?? false);\n                            @endphp";
$newPhp = "@php\n                                \$location = \$r->location;\n                                \$underMaintenance = (bool) (\$location->maintenance_mode ?? false);\n                                \$confirmMsg = \$underMaintenance\n                                    ? 'Take ' . \$location->name . ' out of maintenance mode? Customers will be able to buy again.'\n                                    : 'Set maintenance mode for ' . \$location->name . \"? Customers won't be able to buy vouchures.\";\n                            @endphp";
if (strpos($content, $oldPhp) !== false) {
    $content = str_replace($oldPhp, $newPhp, $content);
    echo "PHP block replaced\n";
} else {
    echo "PHP block not found\n";
}

// Replace the onsubmit line - find anything between onsubmit="return confirm('{{ and }}')">
$pattern = "/onsubmit=\"return confirm\('\{\{.*?\}\}'\)\"/s";
$replacement = "onsubmit=\"return confirm('{{ \$confirmMsg }}')\"";
$content = preg_replace($pattern, $replacement, $content);
echo "Onsubmit regex applied\n";

file_put_contents($path, $content);
echo "Done\n";