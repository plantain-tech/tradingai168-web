<?php
// Retired deliberately: Position Monitor marks come only from the authenticated
// Moomoo OpenD -> Python engine -> platform bridge feed.
http_response_code(410);
header('Content-Type: application/json');
echo '{"error":"disabled","message":"Position Monitor marks are published by Moomoo OpenD."}';
