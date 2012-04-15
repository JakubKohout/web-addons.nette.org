<?php

namespace NetteAddons\Model\Importers\GitHub;

use Nette\Utils\Strings,
	Nette\Http\Url;

/**
 * GitHub helpers
 *
 * @author	Patrik Votoček
 */
class Helpers extends \Nette\Object
{
	public function __construct()
	{
		throw new \NetteAddons\StaticClassException;
	}

	/**
	 * @param string
	 * @return string
	 */
	public static function normalizeRepositoryUrl($url)
	{
		if (Strings::startsWith($url, 'github.com/')) {
			$url = "http://".$url;
		}

		$url = new Url($url);
		$path = substr($url->getPath(), 1);
		if ($url->getHost() != 'github.com' && strpos($path, '/') === FALSE) {
			throw new \NetteAddons\InvalidArgumentException("Invalid github url");
		}
		if (Strings::endsWith($path, '.git')) {
			$path = Strings::substring($path, 0, -4);
		}

		list($vendor, $name) = explode('/', $path);

		$normalized = new Url("https://github.com");
		$normalized->setPath("/$vendor/$name");
		return (string)$normalized;
	}

	/**
	 * JSON string to stdClass or asoc. array
	 *
	 * @param string
	 * @param bool
	 * @return stdClass|NULL
	 */
	public static function decodeJSON($input, $asArray = NULL)
	{
		$output = json_decode($input, $asArray);

		if ($output === NULL) {
			throw new \NetteAddons\InvalidStateException("Invalid JSON");
		}

		return $output;
	}

	/**
	 * @param \NetteAddons\Curl
	 * @param string
	 * @return Repository
	 */
	public static function createRepositoryFromUrl(\NetteAddons\Curl $curl, $url)
	{
		$url = new Url(self::normalizeRepositoryUrl($url));
		$path = substr($url->getPath(), 1);
		list($vendor, $name) = explode('/', $path);
		return new Repository($curl, $vendor, $name);
	}
}