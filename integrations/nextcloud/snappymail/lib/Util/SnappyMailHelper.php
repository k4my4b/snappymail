<?php

namespace OCA\SnappyMail\Util;

class SnappyMailHelper
{

	public function registerHooks()
	{
		$userSession = \OC::$server->getUserSession();
		$userSession->listen('\OC\User', 'postLogin', function($user, $loginName, $password, $isTokenLogin) {
			$config = \OC::$server->getConfig();
			$sEmail = '';
			// Only store the user's password in the current session if they have
			// enabled auto-login using Nextcloud username or email address.
			if ($config->getAppValue('snappymail', 'snappymail-autologin', false)) {
				$sEmail = $user->getUID();
			} else if ($config->getAppValue('snappymail', 'snappymail-autologin-with-email', false)) {
				$sEmail = $config->getUserValue($user->getUID(), 'settings', 'email', '');
			}
			if ($sEmail) {
				static::startApp(true);
				\OC::$server->getSession()['snappymail-sso-hash'] = \RainLoop\Api::CreateUserSsoHash($sEmail, $password/*, array $aAdditionalOptions = array(), bool $bUseTimeout = true*/);
			}
		});

		$userSession->listen('\OC\User', 'logout', function($user) {
			\OC::$server->getSession()['snappymail-sso-hash'] = '';
			static::startApp(true);
			\RainLoop\Api::LogoutCurrentLogginedUser();
		});
	}

	public static function startApp(bool $api = false)
	{
		if (!\class_exists('RainLoop\\Api')) {
			if ($api) {
				$_ENV['SNAPPYMAIL_INCLUDE_AS_API'] = true;
			}
			$_ENV['SNAPPYMAIL_NEXTCLOUD'] = true;
			$_SERVER['SCRIPT_NAME'] = \OC::$server->getAppManager()->getAppWebPath('snappymail') . '/app/index.php';

			$sData = \rtrim(\trim(\OC::$server->getSystemConfig()->getValue('datadirectory', '')), '\\/').'/appdata_snappymail/';
			if (\is_dir($sData)) {
				\define('APP_DATA_FOLDER_PATH', $sData);
			}

			// Nextcloud the default spl_autoload_register() not working
			\spl_autoload_register(function($sClassName){
				$file = RAINLOOP_APP_LIBRARIES_PATH . \strtolower(\strtr($sClassName, '\\', DIRECTORY_SEPARATOR)) . '.php';
				if (is_file($file)) {
					include_once $file;
				}
			});

			require_once \OC::$server->getAppManager()->getAppPath('snappymail') . '/app/index.php';
		}
	}

	/**
	 * @return string
	 */
	public static function getAppUrl()
	{
		$sRequestUri = \OC::$server->getURLGenerator()->linkToRoute('snappymail.page.appGet');
		if ($sRequestUri) {
			return $sRequestUri;
		}
		$sRequestUri = empty($_SERVER['REQUEST_URI']) ? '': \trim($_SERVER['REQUEST_URI']);
		$sRequestUri = \preg_replace('/index.php\/.+$/', 'index.php/', $sRequestUri);
		$sRequestUri = $sRequestUri.'apps/snappymail/app/';
		return '/'.\ltrim($sRequestUri, '/\\');
	}

	/**
	 * @param string $sUrl
	 *
	 * @return string
	 */
	public static function normalizeUrl($sUrl)
	{
		$sUrl = \rtrim(\trim($sUrl), '/\\');
		if ('.php' !== \strtolower(\substr($sUrl, -4))) {
			$sUrl .= '/';
		}

		return $sUrl;
	}

	/**
	 * @param string $sPassword
	 * @param string $sSalt
	 *
	 * @return string
	 */
	public static function encodePassword($sPassword, $sSalt)
	{
		static::startApp(true);
		return \SnappyMail\Crypt::EncryptUrlSafe($sPassword, $sSalt);
	}

	/**
	 * @param string $sPassword
	 * @param string $sSalt
	 *
	 * @return string
	 */
	public static function decodePassword($sPassword, $sSalt)
	{
		static::startApp(true);
		return \SnappyMail\Crypt::DecryptUrlSafe($sPassword, $sSalt);
	}
}
