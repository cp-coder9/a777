<?php
declare(strict_types=1);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) exit(1);
echo stream_socket_get_name($server, false), "\n";
flush();
$socket = stream_socket_accept($server, 10);
if ($socket === false) exit(2);
stream_set_timeout($socket, 5);
fwrite($socket, "220 fixture\r\n");
$data = false;
while (($line = fgets($socket)) !== false) {
    if ($data) {
        if ($line !== ".\r\n") continue;
        if (($argv[1] ?? '') === 'uncertain') break;
        $responses = [
            'malformed' => "garbage\r\n",
            'truncated-250' => '250',
            'truncated-2' => '2',
            'partial-multiline' => "250-pending\r\n",
            'partial-negative' => "450-pending\r\n",
            'truncated-negative' => '550 rejected',
            'unexpected-intermediate' => "354 continue\r\n",
            'inconsistent-multiline' => "250-pending\r\n550 rejected\r\n",
            'unexpected-positive' => "251 forwarded\r\n",
            'negative-450' => "450 unavailable\r\n",
            'negative-550' => "550 rejected\r\n",
            'multiline-accepted' => "250-queued\r\n250 accepted\r\n",
            'bare-lf' => "250 accepted\n",
            'invalid-code' => "299 accepted\r\n",
            'invalid-separator' => "250!accepted\r\n",
        ];
        $mode = $argv[1] ?? '';
        fwrite($socket, $responses[$mode] ?? "250 accepted\r\n");
        if (isset($responses[$mode]) && $mode !== 'multiline-accepted') break;
        $data = false;
        continue;
    }
    if (str_starts_with($line, 'QUIT')) break;
    if (str_starts_with($line, 'DATA')) {
        fwrite($socket, "354 continue\r\n");
        $data = true;
    } else fwrite($socket, "250 ok\r\n");
}
fclose($socket);
fclose($server);
