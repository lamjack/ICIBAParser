<?php
/**
 * Client.php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    jack <linjue@wilead.com>
 * @copyright 2007-16/3/31 WIZ TECHNOLOGY
 * @link      http://wizmacau.com
 * @link      http://jacklam.it
 * @link      https://github.com/lamjack
 * @version
 */

namespace Wiz\Parser;

use GuzzleHttp\Client as GuzzleHttpClient;

/**
 * Class Client
 * @package Wiz\Parser
 */
class Client extends GuzzleHttpClient
{
    /**
     * @var \GuzzleHttp\Client
     */
    private static $instance;

    /**
     * @return GuzzleHttpClient
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new Client([]);
        }

        return static::$instance;
    }

    /**
     * Client constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    /**
     *
     */
    private function __clone()
    {
    }

    /**
     *
     */
    private function __wakeup()
    {
    }
}