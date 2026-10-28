<?php
// Public test numbers for this demo. No real payment details are stored here.
function add_demo_cards(PDO $conn, int $userId): void {
    $insert = $conn->prepare('INSERT INTO cards (user_id, card_number) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM cards WHERE user_id=? AND card_number=?)');
    foreach (['4111111111111111', '4222222222222222', '5555555555554444', '378282246310005', '6011111111111117'] as $number) {
        $insert->execute([$userId, $number, $userId, $number]);
    }
}
