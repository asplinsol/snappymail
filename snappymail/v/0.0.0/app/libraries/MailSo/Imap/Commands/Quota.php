<?php

/*
 * This file is part of MailSo.
 *
 * (c) 2014 Usenko Timur
 * (c) 2021 DJMaze
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * https://datatracker.ietf.org/doc/html/rfc2087
 */

namespace MailSo\Imap\Commands;

/**
 * @category MailSo
 * @package Imap
 */
trait Quota
{
	/**
	 * https://datatracker.ietf.org/doc/html/rfc2087#section-4.2
	 *
	 * @throws \MailSo\Net\Exceptions\Exception
	 * @throws \MailSo\Imap\Exceptions\Exception
	 */
	public function Quota(string $sRootName = '') : ?array
	{
		return $this->getQuota(false, $sRootName);
	}

	/**
	 * https://datatracker.ietf.org/doc/html/rfc2087#section-4.3
	 *
	 * @throws \MailSo\Net\Exceptions\Exception
	 * @throws \MailSo\Imap\Exceptions\Exception
	 */
	public function QuotaRoot(string $sFolderName = 'INBOX') : ?array
	{
		return $this->getQuota(true, $sFolderName);
	}

	// * QUOTA "User quota" (STORAGE 1284645 2097152)\r\n
	private function getQuota(bool $root, string $sFolderName) : ?array
	{
		if (!$this->IsSupported('QUOTA')) {
			return null;
		}
		$oResponseCollection = $this->SendRequest(($root?'GETQUOTAROOT':'GETQUOTA') . " {$this->EscapeFolderName($sFolderName)}");
		$aReturn = array(0, 0);
		foreach ($this->yieldUntaggedResponses() as $oResponse) {
			if ('QUOTA' === $oResponse->StatusOrIndex
				&& isset($oResponse->ResponseList[3])
				&& \is_array($oResponse->ResponseList[3])
				&& 2 < \count($oResponse->ResponseList[3])
				&& 'STORAGE' === \strtoupper($oResponse->ResponseList[3][0])
				&& \is_numeric($oResponse->ResponseList[3][1])
				&& \is_numeric($oResponse->ResponseList[3][2])
			)
			{
				$aReturn = array(
					(int) $oResponse->ResponseList[3][1],
					(int) $oResponse->ResponseList[3][2],
					0,
					0
				);

				if (5 < \count($oResponse->ResponseList[3])
					&& 'MESSAGE' === \strtoupper($oResponse->ResponseList[3][3])
					&& \is_numeric($oResponse->ResponseList[3][4])
					&& \is_numeric($oResponse->ResponseList[3][5])
				)
				{
					$aReturn[2] = (int) $oResponse->ResponseList[3][4];
					$aReturn[3] = (int) $oResponse->ResponseList[3][5];
				}
			}
		}

		return $aReturn;
	}

}
