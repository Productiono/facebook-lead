<?php

namespace FLFBL;

if (!defined('ABSPATH')) {
    exit;
}

class Logger
{
    private string $file_name = 'flfbl-facebook-leads.log';

    public function bootstrap(): void
    {
        $this->ensure_log_file();
    }

    public function log(string $message, array $context = []): void
    {
        $file = $this->ensure_log_file();
        if (!$file) {
            return;
        }
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message;
        if ($context) {
            $line .= ' ' . wp_json_encode($context);
        }
        $line .= PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function ensure_log_file(): string
    {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'flfbl';
        if (!wp_mkdir_p($dir)) {
            return '';
        }
        $file = trailingslashit($dir) . $this->file_name;
        if (!file_exists($file)) {
            file_put_contents($file, '');
        }
        return $file;
    }
}
