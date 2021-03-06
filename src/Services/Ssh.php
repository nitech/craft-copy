<?php

namespace fortrabbit\Copy\Services;

use craft\base\Component;
use fortrabbit\Copy\Exceptions\CraftNotInstalledException;
use fortrabbit\Copy\Exceptions\PluginNotInstalledException;
use fortrabbit\Copy\Exceptions\RemoteException;
use Symfony\Component\Process\Process;

/**
 * Ssh Service
 *
 * @author    Oliver Stark
 * @package   Copy
 * @since     1.0.0
 */
class Ssh extends Component
{
    public const UPLOAD_COMMAND = 'cat {src} | gzip | ssh {remote} "zcat > {target}"';

    public const DOWNLOAD_COMMAND = 'ssh {remote} "cat {src} | gzip" | zcat > {target}';

    public const SSH_EXEC_TIMEOUT = 1200;

    /**
     * @var string
     */
    public $remote;

    /**
     * @var string
     */
    protected $output;

    // Public Methods
    // =========================================================================

    /**
     * Upload a single file
     *
     * @param string $src
     * @param string $target
     *
     * @return bool
     * @throws \fortrabbit\Copy\Exceptions\RemoteException
     */
    public function upload($src, $target)
    {
        $process = new Process($this->getUploadCommand($src, $target));
        $process->setTimeout(self::SSH_EXEC_TIMEOUT);
        $process->run();

        if ($process->isSuccessful()) {
            return true;
        }

        throw new RemoteException($process->getCommandLine() . PHP_EOL . $process->getErrorOutput());
    }

    protected function getUploadCommand(string $src, string $target)
    {
        $cmd = self::UPLOAD_COMMAND;
        $tokens = [
            '{src}' => $src,
            '{target}' => $target,
            '{remote}' => $this->remote,
        ];

        return str_replace(array_keys($tokens), array_values($tokens), $cmd);
    }

    /**
     * Download a single file
     *
     * @param string $src
     * @param string $target
     *
     * @return bool
     * @throws \fortrabbit\Copy\Exceptions\RemoteException
     */
    public function download($src, $target)
    {
        $process = new Process($this->getDownloadCommand($src, $target));
        $process->setTimeout(self::SSH_EXEC_TIMEOUT);
        $process->run();

        if ($process->isSuccessful()) {
            return true;
        }

        throw new RemoteException($process->getCommandLine() . PHP_EOL . $process->getErrorOutput());
    }

    protected function getDownloadCommand(string $src, string $target)
    {
        $cmd = self::DOWNLOAD_COMMAND;
        $tokens = [
            '{src}' => $src,
            '{target}' => $target,
            '{remote}' => $this->remote,
        ];

        return str_replace(array_keys($tokens), array_values($tokens), $cmd);
    }

    /**
     * Plugin check on remote
     *
     * @throws \fortrabbit\Copy\Exceptions\CraftNotInstalledException
     * @throws \fortrabbit\Copy\Exceptions\PluginNotInstalledException
     * @throws \fortrabbit\Copy\Exceptions\RemoteException
     */
    public function checkPlugin()
    {
        $this->exec("php craft help copy");
    }

    /**
     * Execute a command via ssh on remote
     *
     * @param string $cmd
     *
     * @return bool
     * @throws \fortrabbit\Copy\Exceptions\CraftNotInstalledException
     * @throws \fortrabbit\Copy\Exceptions\PluginNotInstalledException
     * @throws \fortrabbit\Copy\Exceptions\RemoteException
     */
    public function exec(string $cmd)
    {
        $cmd = sprintf('ssh %s "%s"', $this->remote, $cmd);
        $process = Process::fromShellCommandline($cmd, CRAFT_BASE_PATH);

        $process->setTimeout(self::SSH_EXEC_TIMEOUT);
        $process->run();

        if ($process->isSuccessful()) {
            $this->output = $process->getOutput();

            return true;
        }

        if (trim($process->getErrorOutput()) == "Could not open input file: craft") {
            throw new CraftNotInstalledException("Craft is not installed on remote.");
        }

        if (stristr($process->getErrorOutput(), "unknown command")) {
            throw new PluginNotInstalledException("Plugin is not installed on remote.");
        }

        throw new RemoteException(
            implode(PHP_EOL, [
                "SSH Remote error: " . $process->getExitCode(),
                "Command: " . $process->getCommandLine(),
                "Output:",
                $process->getErrorOutput()
            ])
        );
    }

    /**
     * Get output of command execution
     *
     * @return mixed
     */
    public function getOutput()
    {
        return $this->output;
    }
}
