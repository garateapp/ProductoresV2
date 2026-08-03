<?php

namespace App\Services\Integrations\Adapters\Handlers;

use App\Contracts\Integrations\SourceAdapterHandler;
use Illuminate\Support\Facades\Storage;

class FtpHandler implements SourceAdapterHandler
{
    public function validateConfiguration(array $config): void
    {
        if (empty($config['host'])) {
            throw new \InvalidArgumentException('La configuración debe incluir host');
        }

        if (empty($config['remote_path'])) {
            throw new \InvalidArgumentException('Debe especificar remote_path');
        }
    }

    public function getSchema(array $config): array
    {
        $localContents = $this->downloadFile($config);
        $fileConfig = array_merge($config, [
            'contents' => $localContents,
            'format' => $config['file_format'] ?? 'csv',
        ]);

        $handler = new FileHandler();
        return $handler->getSchema($fileConfig);
    }

    public function count(array $config): int
    {
        $records = iterator_to_array($this->getRecords($config));
        return count($records);
    }

    public function getRecords(array $config): \Generator
    {
        $localContents = $this->downloadFile($config);
        $fileConfig = array_merge($config, [
            'contents' => $localContents,
            'format' => $config['file_format'] ?? 'csv',
        ]);

        $handler = new FileHandler();

        foreach ($handler->getRecords($fileConfig) as $record) {
            yield $record;
        }
    }

    public function getExamples(array $config): array
    {
        $records = [];
        $count = 0;

        foreach ($this->getRecords($config) as $record) {
            $records[] = $record;
            $count++;

            if ($count >= 5) {
                break;
            }
        }

        return $records;
    }

    public function getStableIdentifier(array $record): string
    {
        return (string) ($record['id'] ?? $record['ID'] ?? md5(json_encode($record)));
    }

    private function downloadFile(array $config): string
    {
        $host = $config['host'];
        $port = $config['port'] ?? 21;
        $username = $config['username'] ?? 'anonymous';
        $password = $config['password'] ?? '';
        $remotePath = $config['remote_path'];
        $protocol = $config['protocol'] ?? 'ftp';
        $timeout = $config['timeout'] ?? 30;
        $passive = $config['passive'] ?? true;

        if ($protocol === 'sftp') {
            return $this->downloadSftp($host, $port, $username, $password, $remotePath, $timeout);
        }

        $conn = @ftp_connect($host, $port, $timeout);

        if ($conn === false) {
            throw new \RuntimeException("No se pudo conectar a {$host}:{$port}");
        }

        try {
            if (!@ftp_login($conn, $username, $password)) {
                throw new \RuntimeException("Error de autenticación FTP en {$host}");
            }

            if ($passive) {
                ftp_pasv($conn, true);
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
            ftp_get($conn, $tempFile, $remotePath, FTP_BINARY);

            $contents = file_get_contents($tempFile);
            unlink($tempFile);

            return $contents;
        } finally {
            ftp_close($conn);
        }
    }

    private function downloadSftp(string $host, int $port, string $username, string $password, string $remotePath, int $timeout): string
    {
        throw new \RuntimeException('SFTP requires phpseclib/phpseclib package. Use FTP instead.');
    }
}
