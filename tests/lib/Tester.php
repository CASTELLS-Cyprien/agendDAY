<?php
declare(strict_types=1);

/**
 * Exécuteur de tests minimal, sans dépendance (le dossier vendor/ est déployé tel
 * quel sur l'hébergement : on évite d'y ajouter PHPUnit et ses dépendances).
 */
final class Tester
{
    /** @var list<array{suite: string, name: string, fn: callable}> */
    private array $tests = [];
    private string $suite = '';
    /** @var array<string, list<callable>> */
    private array $beforeEach = [];

    public function suite(string $name): void
    {
        $this->suite = $name;
    }

    public function beforeEach(callable $fn): void
    {
        $this->beforeEach[$this->suite][] = $fn;
    }

    public function test(string $name, callable $fn): void
    {
        $this->tests[] = ['suite' => $this->suite, 'name' => $name, 'fn' => $fn];
    }

    public function run(?string $filter = null): int
    {
        $passed = $failed = $skipped = 0;
        $currentSuite = null;

        foreach ($this->tests as $test) {
            if ($filter !== null && stripos($test['suite'] . ' ' . $test['name'], $filter) === false) {
                continue;
            }
            if ($test['suite'] !== $currentSuite) {
                $currentSuite = $test['suite'];
                echo PHP_EOL . "■ {$currentSuite}" . PHP_EOL;
            }
            try {
                foreach ($this->beforeEach[$test['suite']] ?? [] as $setup) {
                    $setup();
                }
                ($test['fn'])();
                $passed++;
                echo "  ✔ {$test['name']}" . PHP_EOL;
            } catch (SkipTest $e) {
                $skipped++;
                echo "  ○ {$test['name']} — ignoré : {$e->getMessage()}" . PHP_EOL;
            } catch (\Throwable $e) {
                $failed++;
                echo "  ✘ {$test['name']}" . PHP_EOL;
                echo '      ' . get_class($e) . ' : ' . $e->getMessage() . PHP_EOL;
                echo '      ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
            }
        }

        echo PHP_EOL . sprintf('Résultat : %d réussi(s), %d échoué(s), %d ignoré(s)', $passed, $failed, $skipped) . PHP_EOL;
        return $failed > 0 ? 1 : 0;
    }
}

final class SkipTest extends \RuntimeException
{
}

final class AssertionFailed extends \RuntimeException
{
}

final class Assert
{
    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed(trim($message . ' — attendu ' . self::export($expected) . ', obtenu ' . self::export($actual), ' —'));
        }
    }

    public static function true(mixed $value, string $message = 'Valeur attendue : true'): void
    {
        self::same(true, $value, $message);
    }

    public static function false(mixed $value, string $message = 'Valeur attendue : false'): void
    {
        self::same(false, $value, $message);
    }

    public static function contains(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailed(trim($message . ' — « ' . $needle . ' » absent de : ' . mb_substr($haystack, 0, 300), ' —'));
        }
    }

    public static function notContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new AssertionFailed(trim($message . ' — « ' . $needle . ' » ne devrait pas apparaître', ' —'));
        }
    }

    public static function count(int $expected, array $items, string $message = ''): void
    {
        self::same($expected, count($items), $message ?: 'Nombre d\'éléments');
    }

    private static function export(mixed $value): string
    {
        return is_string($value) ? '"' . $value . '"' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
