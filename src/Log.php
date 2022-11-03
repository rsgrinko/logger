<?php
    /**
     * Copyright (c) 2022 Roman Grinko <rsgrinko@gmail.com>
     * Permission is hereby granted, free of charge, to any person obtaining
     * a copy of this software and associated documentation files (the
     * "Software"), to deal in the Software without restriction, including
     * without limitation the rights to use, copy, modify, merge, publish,
     * distribute, sublicense, and/or sell copies of the Software, and to
     * permit persons to whom the Software is furnished to do so, subject to
     * the following conditions:
     * The above copyright notice and this permission notice shall be included
     * in all copies or substantial portions of the Software.
     * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
     * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
     * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
     * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
     * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
     * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
     * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
     */

    namespace Rsgrinko\Logger;

    class Log
    {
        /**
         * Возможные уровни логирования
         */
        private static $priorityList = [
            LOG_DEBUG   => 'DEBUG',
            LOG_INFO    => 'INFO',
            LOG_NOTICE  => 'NOTICE',
            LOG_WARNING => 'WARNING',
            LOG_ERR     => 'ERROR',
            LOG_CRIT    => 'CRITICAL',
            LOG_ALERT   => 'ALERT',
            LOG_EMERG   => 'EMERGENCY',
        ];

        /**
         * @var string Расположение папки журнала логов
         */
        public static $logPath = '';

        /**
         * Установка расположения папки журнала логов
         *
         * @param string $path Путь до папки журнала логов
         *
         * @return void
         */
        public static function setLogPath(string $path): void
        {
            self::$logPath = $path;
        }

        /**
         * Получение ОС клиента
         *
         * @return string ОС клиента
         */
        private static function getOS(): string
        {
            $undefinedOS = 'Unknown OS';

            if (empty($_SERVER['HTTP_USER_AGENT'])) {
                return $undefinedOS;
            }

            $oses = [
                'iOS'            => '/(iPhone)|(iPad)/i',
                'Windows 3.11'   => '/Win16/i',
                'Windows 95'     => '/(Windows 95)|(Win95)|(Windows_95)/i',
                'Windows 98'     => '/(Windows 98)|(Win98)/i',
                'Windows 2000'   => '/(Windows NT 5.0)|(Windows 2000)/i',
                'Windows XP'     => '/(Windows NT 5.1)|(Windows XP)/i',
                'Windows 2003'   => '/(Windows NT 5.2)/i',
                'Windows Vista'  => '/(Windows NT 6.0)|(Windows Vista)/i',
                'Windows 7'      => '/(Windows NT 6.1)|(Windows 7)/i',
                'Windows 8'      => '/(Windows NT 6.2)|(Windows 8)/i',
                'Windows 8.1'    => '/(Windows NT 6.3)|(Windows 8.1)/i',
                'Windows 10'     => '/(Windows NT 10.0)|(Windows 10)/i',
                'Windows NT 4.0' => '/(Windows NT 4.0)|(WinNT4.0)|(WinNT)|(Windows NT)/i',
                'Windows ME'     => '/Windows ME/i',
                'Open BSD'       => '/OpenBSD/i',
                'Sun OS'         => '/SunOS/i',
                'Android'        => '/Android/i',
                'Linux'          => '/(Linux)|(X11)/i',
                'Macintosh'      => '/(Mac_PowerPC)|(Macintosh)/i',
                'QNX'            => '/QNX/i',
                'BeOS'           => '/BeOS/i',
                'OS/2'           => '/OS/2/i',
                'Search Bot'     => '/(nuhk)|(Googlebot)|(Yammybot)|(Openbot)|(Slurp/cat)|(msnbot)|(ia_archiver)/i',
            ];

            foreach ($oses as $os => $pattern) {
                if (preg_match($pattern, $_SERVER['HTTP_USER_AGENT'])) {
                    return $os;
                }
            }
            return $undefinedOS;
        }

        /**
         * Получение IP адреса посетителя
         *
         * @return string IP адрес
         */
        private static function getIP(): string
        {
            $keys = [
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'REMOTE_ADDR',
            ];
            foreach ($keys as $key) {
                if (!empty($_SERVER[$key])) {
                    $ip = trim(end(explode(',', $_SERVER[$key])));
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
            return 'undefined';
        }

        /**
         * Получение данных текущего окружения
         *
         * @return array Данные окружения
         */
        private static function getEnvironment(): array
        {
            $result = [
                'ip'     => self::getIP(),
                'os'     => self::getOS(),
                'memory' => memory_get_usage(true),
            ];


            $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? gethostname();
            $_SERVER['SERVER_ADDR'] = $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']);

            foreach (['SERVER_ADDR', 'SERVER_NAME', 'HTTP_HOST', 'HTTP_REFERER'] as $value) {
                if (!empty($_SERVER[$value])) {
                    $result[strtolower($value)] = $_SERVER[$value];
                }
            }

            return $result;
        }

        /**
         * Логирование в файл
         *
         * @param string      $text           Тестовое сообщение
         * @param string      $fileName       Имя файла лога
         * @param mixed       $currentContext Контекст (объект, массив, строка...)
         * @param int         $priority       Уровень сообщения
         * @param string|null $system         Название системы
         * @param bool        $addEnv         Флаг необходимости добавления данные окружения
         *
         * @return int                  Количество сохранённых байт
         */
        public static function logToFile(
            string $text,
            string $fileName = '',
            $currentContext  = null,
            int $priority    = LOG_DEBUG,
            string $system   = null,
            bool $addEnv     = true
        ): int {
            if (!array_key_exists($priority, self::$priorityList)) {
                $priority = LOG_DEBUG;
            }

            $text       = str_replace(["\r\n", "\r", "\n"], PHP_EOL, trim($text));
            $logFile    = empty(trim(self::$logPath)) ? $fileName : self::$logPath . '/' . $fileName;

            $logContent = '';
            if (!empty($currentContext)) {
                if (!is_array($currentContext) && !is_object($currentContext)) {
                    $currentContext = [$currentContext];
                }
                $logContent = ' ' . json_encode($currentContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (strpos($logContent, ' [{}') === 0) {
                    $logContent = ' #' . str_replace(PHP_EOL, '\n', serialize($currentContext)) . '#';
                }
            }

            if (empty($system)) {
                $backtraceLimit = 2;
                $backtrace      = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $backtraceLimit);
                if (count($backtrace) < $backtraceLimit) {
                    $system = pathinfo($backtrace[0]['file'], PATHINFO_FILENAME);
                } else {
                    $system = $backtrace[$backtraceLimit - 1]['function'] ?: pathinfo($backtrace[$backtraceLimit - 1]['file'], PATHINFO_FILENAME);
                }
            }

            $logText    = date('[Y-m-d H:i:s] ') . $system . '.' . self::$priorityList[$priority] . ': ';
            $logTextLen = strlen($logText);
            $logText    .= str_replace(PHP_EOL, PHP_EOL . str_repeat(' ', $logTextLen), $text);

            $logTextPosEOL = strpos($logText, PHP_EOL);

            // При необходимости собираем информацию о текущем окружении
            $logEnv = '';
            if ($addEnv) {
                $logEnv = ' ' . json_encode(self::getEnvironment(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $logText .= $logContent;

            if ($logTextPosEOL === false) {
                $logText .= $logEnv;
            } else {
                $logText = preg_replace('/\n/', $logEnv . PHP_EOL, $logText, 1);
            }
            $result = @file_put_contents($logFile, $logText . PHP_EOL, FILE_APPEND | LOCK_EX);

            return $result ?: 0;
        }
    }
