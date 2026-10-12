<?php
declare(strict_types=1);

/**
 * Serveur PHP intégré lancé pour la durée des tests HTTP + client cURL avec cookies.
 */
final class TestServer
{
    private static $process = null;
    public const HOST = '127.0.0.1';
    public const PORT = 8765;

    public static function url(string $path = ''): string
    {
        return 'http://' . self::HOST . ':' . self::PORT . $path;
    }

    public static function start(): void
    {
        if (self::$process !== null) {
            return;
        }

        $root = APP_ROOT;
        $env  = array_merge(getenv(), ['AGENDDAY_CONFIG' => $root . '/tests/config.testing.php']);
        $log  = sys_get_temp_dir() . '/agendday-test-server.log';
        $cmd  = [PHP_BINARY, '-S', self::HOST . ':' . self::PORT, '-t', $root, $root . '/dev/router.php'];

        self::$process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $env);
        if (!is_resource(self::$process)) {
            throw new RuntimeException('Impossible de démarrer le serveur PHP de test.');
        }

        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen(self::HOST, self::PORT, $errno, $errstr, 0.2);
            if ($socket) {
                fclose($socket);
                register_shutdown_function([self::class, 'stop']);
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('Le serveur PHP de test ne répond pas.');
    }

    public static function stop(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
    }
}

final class HttpResponse
{
    public function __construct(public int $status, public array $headers, public string $body)
    {
    }

    public function json(): array
    {
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new AssertionFailed('Réponse JSON attendue, reçu : ' . mb_substr($this->body, 0, 200));
        }
        return $data;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}

final class HttpClient
{
    private string $cookieFile;
    public ?string $csrf = null;

    public function __construct()
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'agd-cookies-');
    }

    public function get(string $path, array $headers = []): HttpResponse
    {
        return $this->request('GET', $path, null, $headers);
    }

    public function post(string $path, array $fields = [], array $headers = []): HttpResponse
    {
        return $this->request('POST', $path, $fields, $headers);
    }

    /** POST avec le jeton CSRF de la dernière page chargée. */
    public function postWithCsrf(string $path, array $fields = []): HttpResponse
    {
        return $this->post($path, $fields + ['csrf_token' => (string) $this->csrf]);
    }

    /** Charge une page HTML et mémorise le jeton CSRF qu'elle contient. */
    public function page(string $path): HttpResponse
    {
        $response = $this->get($path);
        if (preg_match('/name="csrf[-_]token"[^>]*(?:value|content)="([a-f0-9]{64})"/', $response->body, $m)) {
            $this->csrf = $m[1];
        }
        return $response;
    }

    public function login(string $email, string $password): HttpResponse
    {
        $this->page('/connexion');
        $response = $this->postWithCsrf('/connexion', ['email' => $email, 'motDePasse' => $password]);
        $this->page('/calendrier');
        return $response;
    }

    private function request(string $method, string $path, ?array $fields, array $headers): HttpResponse
    {
        $ch = curl_init(TestServer::url($path));
        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if ($fields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Requête HTTP impossible : ' . $method . ' ' . $path);
        }
        // Les cookies ne sont écrits dans le fichier qu'à la fermeture du handle cURL
        return new HttpResponse($status, $responseHeaders, (string) $body);
    }
}
