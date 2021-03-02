<?php

use \RainLoop\Exceptions\ClientException;

class ChangePasswordPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	const
		NAME     = 'Change Password',
		CATEGORY = 'Security',
		DESCRIPTION = '';

	// \RainLoop\Notifications\
	const CouldNotSaveNewPassword = 130;
	const CurrentPasswordIncorrect = 131;
	const NewPasswordShort = 132;
	const NewPasswordWeak = 133;

	public function Init() : void
	{
		$this->UseLangs(true); // start use langs folder

		$this->addJs('js/ChangePasswordUserSettings.js'); // add js file
		$this->addJsonHook('ChangePassword', 'ChangePassword');
		$this->addTemplate('templates/SettingsChangePassword.html');
	}

	protected function getSupportedDrivers(bool $all = false) : iterable
	{
//		foreach (\glob(__DIR__ . '/../change-password-*', GLOB_ONLYDIR) as $file) {
		foreach (\glob(__DIR__ . '/drivers/*.php') as $file) {
			try
			{
				$name = \basename($file, '.php');
				if ($all || $this->Config()->Get('plugin', "driver_{$name}_enabled", false)) {
					require_once $file;
					$class = 'ChangePasswordDriver' . $name;
					if ($class::isSupported()) {
						yield $name => $class;
					}
				}
			}
			catch (\Throwable $oException)
			{
			}
		}
	}

	public function Supported() : string
	{
		foreach ($this->getSupportedDrivers() as $class) {
			return '';
		}
		return 'There are no change-password drivers enabled';
	}

	public function configMapping() : array
	{
		$result = [];
		foreach ($this->getSupportedDrivers(true) as $name => $class) {
			$result[] = \RainLoop\Plugins\Property::NewInstance("driver_{$name}_enabled")
				->SetLabel('Enable ' . $class::NAME)
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDescription($class::DESCRIPTION);
			$result[] = \RainLoop\Plugins\Property::NewInstance("driver_{$name}_allowed_emails")
				->SetLabel('Allowed emails')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING_TEXT)
				->SetDescription('Allowed emails, space as delimiter, wildcard supported. Example: user1@example.net user2@example1.net *@example2.net')
				->SetDefaultValue('*');
			$result = \array_merge($result, \call_user_func("{$class}::configMapping"));
		}
		return $result;
	}

	public function ChangePassword()
	{
		if (!$oAccount->Email()) {
			throw new ClientException(static::CouldNotSaveNewPassword);
		}

		$sPrevPassword = $this->jsonParam('PrevPassword');
		$sNewPassword = $this->jsonParam('NewPassword');

		$oActions = $this->Manager()->Actions();
		$oAccount = $oActions->GetAccount();

		if ($sPrevPassword !== $oAccount->Password()) {
			throw new ClientException(static::CurrentPasswordIncorrect, null, $oActions->StaticI18N('NOTIFICATIONS/CURRENT_PASSWORD_INCORRECT'));
		}

		$sPasswordForCheck = \trim($sNewPassword);
		if (10 > \strlen($sPasswordForCheck)) {
			throw new ClientException(static::NewPasswordShort, null, $oActions->StaticI18N('NOTIFICATIONS/NEW_PASSWORD_SHORT'));
		}

		if (!\MailSo\Base\Utils::PasswordWeaknessCheck($sPasswordForCheck)) {
			throw new ClientException(static::NewPasswordWeak, null, $oActions->StaticI18N('NOTIFICATIONS/NEW_PASSWORD_WEAK'));
		}

		$bResult = false;
		$oConfig = $this->Config();
		foreach ($this->getSupportedDrivers() as $name => $class) {
			if (\RainLoop\Plugins\Helper::ValidateWildcardValues($oAccount->Email(), $oConfig->Get('plugin', "driver_{$name}_allowed_emails"))) {
				$name = $class::NAME;
				try
				{
					$oDriver = new $class(
						$oConfig(),
						$oActions->Logger()
					);
					if (!$oDriver->ChangePassword($oAccount, $sPrevPassword, $sNewPassword)) {
						throw new ClientException(static::CouldNotSaveNewPassword);
					}
					$bResult = true;
					if ($this->oLogger) {
						$this->oLogger->Write("{$name} password changed for {$oAccount->Email()}");
					}
				}
				catch (\Throwable $oException)
				{
					if ($this->oLogger) {
						$this->oLogger->Write("ERROR: {$name} password change for {$oAccount->Email()} failed");
						$this->oLogger->WriteException($oException);
//						$this->oLogger->WriteException($oException, \MailSo\Log\Enumerations\Type::WARNING, $name);
					}
				}
			}
		}

		if ($bResult) {
			$oAccount->SetPassword($sNewPassword);
			$oActions->SetAuthToken($oAccount);
		}

		return $oActions->GetSpecAuthToken();
//		return $this->jsonResponse(__FUNCTION__, $oActions->GetSpecAuthToken());
	}

	public static function encrypt(string $algo, string $password)
	{
		switch (\strtolower($algo))
		{
			case 'argon2i':
				return \password_hash($password, PASSWORD_ARGON2I);

			case 'argon2id':
				return \password_hash($password, PASSWORD_ARGON2ID);

			case 'bcrypt':
				return \password_hash($password, PASSWORD_BCRYPT);

			case 'sha256-crypt':
				return \crypt($password,'$5$'.\substr(\base64_encode(\random_bytes(32)), 0, 16));

			case 'sha512-crypt':
				return \crypt($password,'$6$'.\substr(\base64_encode(\random_bytes(32)), 0, 16));

			default:
				break;
		}

		return $sPassword;
	}

}