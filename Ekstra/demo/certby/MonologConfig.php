<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\DatabaseHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\LogRecord;
use PDO;

class MonologConfig {
    private static $loggers = [];
    public static function getSystemLogger() {
        if (!isset(self::$loggers['system'])) {
            self::$loggers['system'] = self::createSystemLogger();
        }
        return self::$loggers['system'];
    }
    public static function getMailLogger() {
        if (!isset(self::$loggers['mail'])) {
            self::$loggers['mail'] = self::createMailLogger();
        }
        return self::$loggers['mail'];
    }
    private static function createSystemLogger() {
        $logger = new Logger('system');
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $fileHandler = new RotatingFileHandler(
            $logDir . '/system.log',
            0, 
            Logger::DEBUG
        );
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        );
        $fileHandler->setFormatter($formatter);
        $dbHandler = self::createDatabaseHandler('system_logs');
        $logger->pushHandler($fileHandler);
        $logger->pushHandler($dbHandler);
        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(function ($record) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['user_id'])) {
                $record->extra['user_id'] = $_SESSION['user_id'];
                $record->extra['user_name'] = $_SESSION['user_name'] ?? 'Unknown';
                $record->extra['user_role'] = $_SESSION['user_role'] ?? 'Unknown';
            }
            return $record;
        });
        
        return $logger;
    }
    private static function createMailLogger() {
        $logger = new Logger('mail');
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $fileHandler = new RotatingFileHandler(
            $logDir . '/mail.log',
            0,
            Logger::INFO
        );
        
        $formatter = new LineFormatter(
            "[%datetime%] %level_name%: %message% %context%\n",
            'Y-m-d H:i:s'
        );
        $fileHandler->setFormatter($formatter);
        $dbHandler = self::createMailDatabaseHandler();
        $logger->pushHandler($fileHandler);
        $logger->pushHandler($dbHandler);
        
        return $logger;
    }
    private static function createDatabaseHandler($table) {
        return new class($table) extends \Monolog\Handler\AbstractProcessingHandler {
            private $table;
            private $pdo;
            
            public function __construct($table) {
                parent::__construct(Logger::DEBUG);
                $this->table = $table;
                $this->pdo = getConnection();
            }
            
            
            protected function write(LogRecord $record): void {
                try {
                    $sql = "INSERT INTO {$this->table} 
                            (user_id, log_type, content, ip_address, created_at, level, context) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        $record->extra['user_id'] ?? null,
                        $this->mapLevelToType($record->level),
                        $record->message,
                        $record->extra['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
                        $record->datetime->format('Y-m-d H:i:s'),
                        $record->level->name,
                        json_encode($record->context)
                    ]);
                } catch (Exception $e) {
                    error_log("Monolog DB write error: " . $e->getMessage());
                }
            }
            
            private function mapLevelToType($level) {
                switch ($level->value) {
                    case Logger::INFO:
                        return 'info';
                    case Logger::WARNING:
                        return 'warning';
                    case Logger::ERROR:
                        return 'error';
                    case Logger::DEBUG:
                        return 'debug';
                    default:
                        return 'info';
                }
            }
        };
    }
    private static function createMailDatabaseHandler() {
        return new class extends \Monolog\Handler\AbstractProcessingHandler {
            private $pdo;
            
            public function __construct() {
                parent::__construct(Logger::INFO);
                $this->pdo = getConnection();
            }
            protected function write(LogRecord $record): void {
                try {
                    $context = $record->context;
                    
                    $sql = "INSERT INTO mail_history 
                            (recipient_email, subject, content, sent_at, status, error_message) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        $context['recipient'] ?? null,
                        $context['subject'] ?? null,
                        $context['body'] ?? $record->message,
                        $record->datetime->format('Y-m-d H:i:s'),
                        $context['status'] ?? 'sent',
                        $context['error'] ?? null
                    ]);
                } catch (Exception $e) {
                    error_log("Monolog mail DB write error: " . $e->getMessage());
                }
            }
        };
    }
}
function logUserLogin($userId, $userName, $userRole) {
    $logger = MonologConfig::getSystemLogger();
    $logger->info('User logged in', [
        'action' => 'login',
        'user_id' => $userId,
        'user_name' => $userName,
        'user_role' => $userRole
    ]);
}

function logUserLogout($userId, $userName) {
    $logger = MonologConfig::getSystemLogger();
    $logger->info('User logged out', [
        'action' => 'logout',
        'user_id' => $userId,
        'user_name' => $userName
    ]);
}

function logMailSent($recipient, $subject, $body, $status = 'sent', $error = null) {
    $logger = MonologConfig::getMailLogger();
    
    if ($status === 'sent') {
        $logger->info('Mail sent successfully', [
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => $status
        ]);
    } else {
        $logger->error('Mail sending failed', [
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => $status,
            'error' => $error
        ]);
    }
}

function logSystemEvent($message, $level = 'info', $context = []) {
    $logger = MonologConfig::getSystemLogger();
    
    switch ($level) {
        case 'debug':
            $logger->debug($message, $context);
            break;
        case 'warning':
            $logger->warning($message, $context);
            break;
        case 'error':
            $logger->error($message, $context);
            break;
        default:
            $logger->info($message, $context);
    }
}
?>