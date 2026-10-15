<?php
/**
 * Classe Conexao
 * Gerencia a conexão com o banco de dados MySQL via PDO carregando variáveis de ambiente do arquivo .env ou config.php.
 */

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

class Conexao {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
        }
        $rootDir = dirname(__DIR__);
        if (file_exists($rootDir . '/.env')) {
            $this->carregarEnv($rootDir . '/.env');
        } elseif (file_exists($rootDir . '/.env.example')) {
            $this->carregarEnv($rootDir . '/.env.example');
        } elseif (file_exists(__DIR__ . '/.env')) {
            $this->carregarEnv(__DIR__ . '/.env');
        }

        $this->host = $this->obterConfig('DB_HOST', 'localhost');
        $this->username = $this->obterConfig('DB_USER', 'root');
        $this->password = $this->obterConfig('DB_PASS', '');
        $this->db_name = $this->obterConfig('DB_NAME', 'urna');
    }

    /**
     * Obtém valor de configuração respeitando a ordem:
     * Constante definida > getenv() > $_ENV > $_SERVER > valor padrão
     */
    private function obterConfig($key, $default = '') {
        if (defined($key)) {
            return constant($key);
        }
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }

    /**
     * Carrega variáveis a partir do arquivo .env se existir
     *
     * @param string $path Caminho para o arquivo .env
     */
    private function carregarEnv($path) {
        if (!file_exists($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                
                // Só define se a variável ainda não estiver definida no ambiente
                $atual = $this->obterConfig($key, null);
                if ($atual === null || $atual === '') {
                    if (function_exists('putenv')) {
                        @putenv("{$key}={$val}");
                    }
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }

    /**
     * Retorna a conexão com o banco de dados.
     * 
     * @return PDO|null Instância de PDO ativa
     */
    public function conectar() {
        $this->conn = null;

        $port = $this->obterConfig('DB_PORT', '3306');

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";port=" . $port . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $exception) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Erro na conexão com o banco de dados: " . $exception->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        return $this->conn;
    }

    /**
     * Método estático facilitador para obter conexão.
     * 
     * @return PDO|null
     */
    public static function getConexao() {
        $instancia = new self();
        return $instancia->conectar();
    }
}
