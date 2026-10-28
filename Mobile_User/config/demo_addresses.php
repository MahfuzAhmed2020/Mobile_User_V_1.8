<?php
function add_demo_addresses(PDO $conn, int $userId): void {
    $samples = [
        ['123 Main Street', 'New York', 'NY', '10001', 'USA'],
        ['456 Oak Avenue', 'Los Angeles', 'CA', '90001', 'USA'],
        ['789 Pine Road', 'Chicago', 'IL', '60601', 'USA'],
        ['101 Maple Lane', 'Houston', 'TX', '77001', 'USA'],
        ['202 Cedar Boulevard', 'Phoenix', 'AZ', '85001', 'USA'],
    ];
    foreach (['addresses', 'addresses_whitelist'] as $table) {
        $insert = $conn->prepare("INSERT INTO $table (user_id,address_line,city,state,zip,country) SELECT ?,?,?,?,?,? WHERE NOT EXISTS (SELECT 1 FROM $table WHERE user_id=? AND address_line=? AND city=? AND state=? AND zip=? AND country=?)");
        foreach ($samples as $address) {
            $insert->execute(array_merge([$userId], $address, [$userId], $address));
        }
    }
}
