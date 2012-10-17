<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005-2006 Robert Lemke (robert@typo3.org)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * SOAP server for the TYPO3 Extension Repository
 *
 * $Id$
 *
 * @author	Robert Lemke <robert@typo3.org>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   83: class tx_ter_api
 *   98:     public function __construct($parentObj)
 *
 *              SECTION: PUBLIC API
 *  122:     public function ping ($value)
 *  136:     public function uploadExtension ($accountData, $extensionInfoData, $filesData)
 *  168:     public function checkExtensionKey ($accountData, $extensionKey)
 *  193:     public function registerExtensionKey ($accountData, $registerExtensionKeyData)
 *  224:     public function getExtensionKeys ($accountData, $extensionKeyFilterOptions)
 *  268:     public function deleteExtensionKey ($accountData, $extensionKey)
 *  318:     public function modifyExtensionKey ($accountData, $modifyExtensionKeyData)
 *  347:     public function setReviewState ($accountData, $setReviewStateData)
 *  377:     public function increaseExtensionDownloadCounter ($accountData, $extensionVersionData, $incrementor)
 *
 *              SECTION: uploadExtension helper functions
 *  423:     protected function uploadExtension_writeExtensionAndIconFile (&$extensionInfoData, $filesData)
 *  561:     protected function uploadExtension_writeExtensionInfoToDB ($accountData, $extensionInfoData, $filesData)
 *
 *              SECTION: checkExtensionKey helper functions
 *  690:     protected function checkExtensionKey_extensionKeyIsFormallyValid ($extensionKey)
 *
 *              SECTION: registerExtensionKey helper functions
 *  738:     protected function registerExtensionKey_writeExtensionKeyInfoToDB ($accountData, $extensionKeyData)
 *
 *              SECTION: modifyExtensionKey helper functions
 *  775:     protected function modifyExtensionKey_writeModifiedKeyRecordIntoDB ($accountData, $modifyExtensionKeyData)
 *
 *              SECTION: setReviewState helper functions
 *  819:     protected function setReviewState_writeNewStateIntoDB ($setReviewStateData)
 *
 *              SECTION: increaseExtensionDownloadCounter helper functions
 *  859:     protected function increaseExtensionDownloadCounter_increaseCounterInDB ($extensionVersionData, $incrementor)
 *
 * TOTAL FUNCTIONS: 17
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(t3lib_extMgm::extPath('ter').'class.tx_ter_helper.php');

/**
 * TYPO3 Extension Repository, SOAP Server
 *
 * @author	Robert Lemke <robert@typo3.org>
 * @package TYPO3
 * @subpackage tx_ter_soapserver
 */
class tx_ter_api {

	protected	$helperObj;
	protected	$parentObj;
	protected	$cObj;
	protected	$tce;

	protected	$extensionMaxUploadSize = 31457280;					// 30MB Maximum upload size for extensions

	/**
	 * Constructor
	 *
	 * @param	object		$parentObj: Reference to parent object
	 * @return	void
	 * @access	public
	 */
	public function __construct($parentObj) {
		$this->helperObj = new tx_ter_helper ($parentObj);
		$this->parentObj = $parentObj;
		$this->cObj = $parentObj->cObj;
	}





	/*********************************************************
	 *
	 * PUBLIC API
	 *
	 *********************************************************/

	/**
	 * Test method which just returns the given valued prepended with "pong"
	 *
	 * @param	string		$value
	 * @return	string		$value prepended with "pong"
	 * @access	public
	 * @since	2.0.0
	 */
	public function ping ($value) {
		return 'pong'.$value;
	}

	/**
	 * Checks for valid account data
	 *
	 * @param	object		$accountData: Username and passwords for auth check
	 * @return bool
	 * @access	public
	 * @since	2.0.0
	 */
	public function login($accountData) {
		return $this->helperObj->checkValidUser($accountData);
	}


	/**
	 * Method for uploading an extension to the repository
	 *
	 * @param	object		$accountData: Username and passwords for upload the extension
	 * @param	object		$extensionInfoData: The general extension information as received by the SOAP server
	 * @param	array		$filesData: The array of file data objects as received by the SOAP server
	 * @return	object		uploadExtensionResult object if upload was successful, otherwise a SoapFault exception is thrown.
	 * @access	public
	 * @since	2.0.0
	 */
	public function uploadExtension ($accountData, $extensionInfoData, $filesData) {
		global $TSFE, $TYPO3_DB;

		if (TYPO3_DLOG) t3lib_div::devLog('tx_ter_api->uploadExtension()', 'ter', 0, 'Upload of extension '.$extensionInfoData->extensionKey.' ('.$extensionInfoData->version.') by user '.$accountData->username);

		$uploadUserRecordArr = $this->helperObj->getValidUser ($accountData);
		$extensionKeyRecordArr = $this->helperObj->getExtensionKeyRecord ($extensionInfoData->extensionKey);
		if ($extensionKeyRecordArr == FALSE) throw new SoapFault (TX_TER_ERROR_UPLOADEXTENSION_EXTENSIONDOESNTEXIST, 'Extension does not exist.');
		if (strtolower($extensionKeyRecordArr['ownerusername']) !== strtolower($accountData->username) && $uploadUserRecordArr['admin'] !== TRUE) throw new SoapFault (TX_TER_ERROR_UPLOADEXTENSION_ACCESSDENIED, 'Access denied.');

		$this->uploadExtension_writeExtensionAndIconFile ($extensionInfoData, $filesData);
		$this->uploadExtension_writeExtensionInfoToDB ($accountData, $extensionInfoData, $filesData);
		$this->helperObj->requestUpdateOfExtensionIndexFile();

		return array (
			'resultCode' => TX_TER_RESULT_EXTENSIONSUCCESSFULLYUPLOADED,
			'resultMessages' => array(
				'Please note that it might take a while (up to one day) until your extension and the documentation appear on TYPO3.org.'
			),
			'version' => $extensionInfoData->version,
		);
	}

	/**
	 * Method for deleting an extension version from the repository
	 *
	 * @param	object		$accountData: Username and passwords for upload the extension (admin account required)
	 * @param	string		$extensionKey: Extension key of the extension version to delete
	 * @param	string		$version: Version string of the extension version to delete
	 * @return	object		simpleResult object if extension could be deleted, otherwise a SoapFault exception is thrown.
	 * @access	public
	 * @since	2.0.1
	 */
	public function deleteExtension ($accountData, $extensionKey, $version) {
		global $TSFE, $TYPO3_DB;

		if (TYPO3_DLOG) t3lib_div::devLog('tx_ter_api->deleteExtension()', 'ter', 0, 'Deletion of extension '.$extensionKey.' ('.$version.') by user '.$accountData->username);

		$userRecordArr = $this->helperObj->getValidUser ($accountData);
		if ($userRecordArr['admin'] !== TRUE) throw new SoapFault (TX_TER_ERROR_DELETEEXTENSION_ACCESS_DENIED, 'Access denied. You must be administrator in order to delete extensions');
		$extensionKeyRecordArr = $this->helperObj->getExtensionKeyRecord ($extensionKey);
		if ($extensionKeyRecordArr == FALSE) throw new SoapFault (TX_TER_ERROR_DELETEEXTENSION_EXTENSIONDOESNTEXIST, 'Extension does not exist.');

		$this->deleteExtension_deleteFromDBAndRemoveFiles($extensionKey, $version);
		$this->helperObj->requestUpdateOfExtensionIndexFile();

		return array (
			'resultCode' => TX_TER_RESULT_EXTENSIONSUCCESSFULLYDELETED,
			'resultMessages' => array()
		);
	}

	/**
	 * Checks if an extension key already exists
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	string		$extensionKey: The extension key to check
	 * @return	object		simpleResult object if key could be checked, otherwise a SoapFault exception is thrown.
	 * @access	public
	 * @since	2.0.0
	 */
	public function checkExtensionKey ($accountData, $extensionKey) {
		global $TSFE, $TYPO3_DB;
		$userRecordArr = $this->helperObj->getValidUser ($accountData);

		if ($this->checkExtensionKey_extensionKeyIsFormallyValid ($extensionKey)) {
			$resultCode = $this->helperObj->extensionKeyIsAvailable ($extensionKey) ? TX_TER_RESULT_EXTENSIONKEYDOESNOTEXIST : TX_TER_RESULT_EXTENSIONKEYALREADYEXISTS;
		} else {
			$resultCode = TX_TER_RESULT_EXTENSIONKEYNOTVALID;
		}

		return array (
			'resultCode' => $resultCode,
			'resultMessages' => array(),
		);
	}

	/**
	 * Registers an extension key
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	object		$extensionKeyData: The extension key and other information
	 * @return	object		simpleResult object if key was registered, otherwise a SoapFault exception is thrown.
	 * @access	public
	 * @since	2.0.0
	 */
	public function registerExtensionKey ($accountData, $registerExtensionKeyData) {
		global $TSFE, $TYPO3_DB;

		$userRecordArr = $this->helperObj->getValidUser ($accountData);
		if ($this->checkExtensionKey_extensionKeyIsFormallyValid ($registerExtensionKeyData->extensionKey)) {
            if($this->helperObj->extensionKeyIsAvailable ($registerExtensionKeyData->extensionKey)) {
				$this->registerExtensionKey_writeExtensionKeyInfoToDB ($accountData, $registerExtensionKeyData);
				$resultCode = TX_TER_RESULT_EXTENSIONKEYSUCCESSFULLYREGISTERED;
            } else {
				$resultCode = TX_TER_RESULT_EXTENSIONKEYALREADYEXISTS;
            }
		} else {
			$resultCode = TX_TER_RESULT_EXTENSIONKEYNOTVALID;
		}

		return array (
			'resultCode' => $resultCode,
			'resultMessages' => array(),
		);
	}

	/**
	 * Returns a list of extension key records filtered by certain
	 * criteria.
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	object		$extensionKeyFilterOptions: Result will be filtered by fields set in these filter options
	 * @return	object		getExtensionKeyRepsonse-object if key(s) could be fetched (might also be an empty result). A SoapFault exception is thrown if an error ocurred.
	 * @access	public
	 * @since	2.0.0
	 */
	public function getExtensionKeys ($accountData, $extensionKeyFilterOptions) {
		global $TSFE, $TYPO3_DB;

		$userRecordArr = $this->helperObj->getValidUser ($accountData);
		$extensionKeyDataArr = array();

		$whereClause = 'pid='.intval($this->parentObj->extensionsPID);
		if (isset ($extensionKeyFilterOptions->username)) $whereClause .= ' AND ownerusername LIKE "'.$TYPO3_DB->quoteStr ($extensionKeyFilterOptions->username, 'tx_ter_extensionkeys').'"';
		if (isset ($extensionKeyFilterOptions->title)) $whereClause .= ' AND title LIKE "'.$TYPO3_DB->quoteStr ($extensionKeyFilterOptions->title, 'tx_ter_extensionkeys').'"';
		if (isset ($extensionKeyFilterOptions->description)) $whereClause .= ' AND description LIKE "'.$TYPO3_DB->quoteStr ($extensionKeyFilterOptions->description, 'tx_ter_extensionkeys').'"';
		if (isset ($extensionKeyFilterOptions->extensionKey)) $whereClause .= ' AND extensionkey LIKE "'.$TYPO3_DB->quoteStr ($extensionKeyFilterOptions->extensionKey, 'tx_ter_extensionkeys').'"';

		$res = $TYPO3_DB->exec_SELECTquery (
			'extensionkey,title,description,ownerusername',
			'tx_ter_extensionkeys',
			$whereClause
		);

		if ($res) {
			while ($row = $TYPO3_DB->sql_fetch_assoc ($res)) {
				$extensionKeyDataArr[] = $row;
			}
			$resultCode = TX_TER_RESULT_GENERAL_OK;
		} else {
			throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while fetching extension keys.');
		}

		return array (
			'simpleResult' => array (
				'resultCode' => $resultCode,
				'resultMessages' => array()
			),
			'extensionKeyData' => $extensionKeyDataArr
		);
	}

	/**
	 * Deletes an extension key. Only possible if no uploaded versions exist.
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	string		$extensionKey: The extension key to delete
	 * @return	object		simpleResponse-object. A SoapFault exception is thrown if a fatal error ocurred.
	 * @access	public
	 * @since	2.0.0
	 */
	public function deleteExtensionKey ($accountData, $extensionKey) {
		global $TSFE, $TYPO3_DB;

		$userRecordArr = $this->helperObj->getValidUser ($accountData);
		$extensionKeyRecordArr = $this->helperObj->getExtensionKeyRecord ($extensionKey);

		if (is_array ($extensionKeyRecordArr)) {

			if ($extensionKeyRecordArr['ownerusername'] != $accountData->username && $userRecordArr['admin'] !== TRUE) throw new SoapFault (TX_TER_ERROR_DELETEEXTENSIONKEY_ACCESSDENIED, 'Access denied.');

			$res = $TYPO3_DB->exec_SELECTquery (
				'extensionkey',
				'tx_ter_extensions',
				'extensionkey="'.$TYPO3_DB->quoteStr($extensionKey, 'tx_ter_extensions').'" AND pid='.intval($this->parentObj->extensionsPID)
			);

			if ($res) {
				if ($TYPO3_DB->sql_num_rows($res) > 0) {
					$resultCode = TX_TER_ERROR_DELETEEXTENSIONKEY_CANTDELETEBECAUSEVERSIONSEXIST;
				} else {
					$res = $TYPO3_DB->exec_DELETEquery (
						'tx_ter_extensionkeys',
						'extensionkey="'.$TYPO3_DB->quoteStr($extensionKey, 'tx_ter_extensions').'" AND pid='.intval($this->parentObj->extensionsPID)
					);
					if (!$res) throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while deleting extension key.');

					$resultCode = TX_TER_RESULT_GENERAL_OK;
				}
			} else {
				throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while fetching versions.');
			}
		} else {
			$resultCode = TX_TER_ERROR_DELETEEXTENSIONKEY_KEYDOESNOTEXIST;
		}

		return array (
			'resultCode' => $resultCode,
			'resultMessages' => array()
		);
	}

	/**
	 * Modifies an extension key.
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	object		$modifyExtensionKeyData: Fields which should be changed, "extensionKey" is mandatory.
	 * @return	object		simpleResponse-object. A SoapFault exception is thrown if a fatal error ocurred.
	 * @access	public
	 * @since	2.0.0
	 */
	public function modifyExtensionKey ($accountData, $modifyExtensionKeyData) {
		global $TSFE, $TYPO3_DB;

		$userRecordArr = $this->helperObj->getValidUser ($accountData);
		$extensionKeyRecordArr = $this->helperObj->getExtensionKeyRecord ($modifyExtensionKeyData->extensionKey);

		if (is_array ($extensionKeyRecordArr)) {
			if ($extensionKeyRecordArr['ownerusername'] != $accountData->username && $userRecordArr['admin'] !== TRUE) throw new SoapFault (TX_TER_ERROR_MODIFYEXTENSIONKEY_ACCESSDENIED, 'Access denied.');
			$resultCode = $this->modifyExtensionKey_writeModifiedKeyRecordIntoDB ($accountData, $modifyExtensionKeyData);
			$this->helperObj->requestUpdateOfExtensionIndexFile();
		} else {
			$resultCode = TX_TER_ERROR_MODIFYEXTENSIONKEY_KEYDOESNOTEXIST;
		}

		return array (
			'resultCode' => $resultCode,
			'resultMessages' => array()
		);
	}

	/**
	 * Sets the review state of an extension version
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	object		$setReviewStateData: The extension key, version number and the new review state (integer)
	 * @return	object		simpleResponse-object. A SoapFault exception is thrown if a fatal error ocurred.
	 * @access	public
	 * @since	2.0.0
	 */
	public function setReviewState ($accountData, $setReviewStateData) {
		global $TSFE, $TYPO3_DB;

		$userRecordArr = $this->helperObj->getValidUser ($accountData);
		$reviewersFrontendUsergroupUid = intval($this->parentObj->conf['reviewersFrontendUsergroupUid']);

		if ($reviewersFrontendUsergroupUid == 0) throw new SoapFault (TX_TER_ERROR_SETREVIEWSTATE_NOUSERGROUPDEFINED, 'Warning: No usergroup for reviewers has been defined on the server side. Aborting ...');
		if (!t3lib_div::inList($userRecordArr['usergroup'], $reviewersFrontendUsergroupUid)) throw new SoapFault (TX_TER_ERROR_SETREVIEWSTATE_ACCESSDENIED, 'Access denied.');

		$this->setReviewState_writeNewStateIntoDB($setReviewStateData);

#		Regeneration of index file is currently deactived:
#		$this->helperObj->requestUpdateOfExtensionIndexFile();

		return array (
			'resultCode' => TX_TER_RESULT_GENERAL_OK,
			'resultMessages' => array()
		);
	}

	/**
	 * Increases the download counters of several extension version
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	object		$extensionVersionsAndIncrementors: An array of extension keys, version numbers and the incrementor
	 * @return	object		simpleResponse-object. A SoapFault exception is thrown if a fatal error ocurred.
	 * @access	public
	 * @since	2.0.0
	 */
	public function increaseExtensionDownloadCounters ($accountData, $extensionVersionsAndIncrementors) {
		global $TSFE, $TYPO3_DB;
		$errorMessages = array();

		t3lib_div::devLog('DownloadCounter: Trying to increase extension download counters - user "' . $accountData->username . '" connected from IP "' . t3lib_div::getIndpEnv('REMOTE_ADDR') . '" - number of results: ' . count($extensionVersionsAndIncrementors->extensionVersionAndIncrementor), 'tx_ter_api', 0);

		$userRecordArr = $this->helperObj->getValidUser($accountData);
		$mirrorsFrontendUsergroupUid = intval($this->parentObj->conf['mirrorsFrontendUsergroupUid']);

		if ($mirrorsFrontendUsergroupUid == 0) {
			t3lib_div::devLog('DownloadCounter: No usergroup for mirrors has been defined on the server side. Aborting ...', 'tx_ter_api', 3);
			throw new SoapFault (TX_TER_ERROR_INCREASEEXTENSIONDOWNLOADCOUNTER_NOUSERGROUPDEFINED, 'Warning: No usergroup for mirrors has been defined on the server side. Aborting ...');
		}
		if (!t3lib_div::inList($userRecordArr['usergroup'], $mirrorsFrontendUsergroupUid)) {
			t3lib_div::devLog('DownloadCounter: Access denied for user ' . $accountData->username, 'tx_ter_api', 3, $userRecordArr);
			throw new SoapFault (TX_TER_ERROR_INCREASEEXTENSIONDOWNLOADCOUNTER_ACCESSDENIED, 'Access denied.');
		}

		$counter = 0;
		if (is_array($extensionVersionsAndIncrementors->extensionVersionAndIncrementor)) {
			foreach ($extensionVersionsAndIncrementors->extensionVersionAndIncrementor as $extensionVersionAndIncrementor) {
				try {
					$this->increaseExtensionDownloadCounter_increaseCounterInDB($extensionVersionAndIncrementor);
					$counter++;
				} catch (SoapFault $exception) {
					$errorMessages[] = '['.$extensionVersionAndIncrementor->extensionKey.']['.$extensionVersionAndIncrementor->version.'] '.$exception->faultstring;
				}
			}
		} else {
			try {
				$extensionVersionAndIncrementor = $extensionVersionsAndIncrementors->extensionVersionAndIncrementor;
				$this->increaseExtensionDownloadCounter_increaseCounterInDB($extensionVersionAndIncrementor);
				$counter++;
			} catch (SoapFault $exception) {
				$errorMessages[] = '['.$extensionVersionAndIncrementor->extensionKey.']['.$extensionVersionAndIncrementor->version.'] '.$exception->faultstring;
			}
		}

		t3lib_div::devLog('DownloadCounter: Increased download counter for ' . $counter . ' extensions. User "' . $accountData->username . '".', 'tx_ter_api', 0);
			// Update extension index file
		$this->helperObj->requestUpdateOfExtensionIndexFile();

			// Return results including list of error messages if any
		if (count($errorMessages) > 0) {
			t3lib_div::devLog('DownloadCounter: Errors occured for user "' . $accountData->username . '".', 'tx_ter_api', 2, $errorMessages);
			$result = array (
				'resultCode' => TX_TER_RESULT_ERRORS_OCCURRED,
				'resultMessages' => $errorMessages
			);
		}
		else {
			t3lib_div::devLog('DownloadCounter: Everything OK. User "' . $accountData->username . '".', 'tx_ter_api', -1);
			$result = array (
				'resultCode' => TX_TER_RESULT_GENERAL_OK,
				'resultMessages' => array()
			);
		}
		return $result;
	}







	/*********************************************************
	 *
	 * uploadExtension helper functions
	 *
	 *********************************************************/

	/**
	 * Creates a T3X file by using the extension info data and the files data and
	 * writes the file to the repository's directory. By default this function creates
	 * gzip compressed T3X files.
	 *
	 * After writing the extension, some files specififc data (like the filesize) is
	 * added to $extensionInfoData for later informational use.
	 *
	 * @param	object		$extensionInfoData: The general extension information as received by the SOAP server
	 * @param	object		$filesData: The array of file data objects as received by the SOAP server
	 * @return	void
	 * @access	protected
	 */
	protected function uploadExtension_writeExtensionAndIconFile (&$extensionInfoData, $filesData) {

		if (!@is_dir ($this->parentObj->repositoryDir)) throw new SoapFault (TX_TER_ERROR_GENERAL_EXTREPDIRDOESNTEXIST, 'Extension repository directory does not exist.');
		//t3lib_div::devLog($filesData->fileData,'filesData->fileData',0);
		if (!is_array ($filesData->fileData)) throw new SoapFault (TX_TER_ERROR_GENERAL_EXTENSIONCONTAINSNOFILES, 'Extension contains no files.');

			// Prepare Files Data Array:
		$preparedFilesDataArr = array();
		foreach ($filesData->fileData as $fileData) {

			$decodedContent = base64_decode ($fileData->content);
			if ($fileData->contentMD5 != md5 ($decodedContent)) throw new SoapFault (TX_TER_ERROR_UPLOADEXTENSION_FILEMD5DOESNOTMATCH, 'MD5 does not match for file '.(string)$fileData->name);

			$preparedFilesDataArr[$fileData->name] = array (
				'name' => $fileData->name,
				'size' => $fileData->size,
				'mtime' => $fileData->modificationTime,
				'is_executable' => $fileData->isExecutable,
				'content' => $decodedContent,
				'content_md5' => md5 ($decodedContent)
			);
		}

			// Create an old-style list of dependencies, conflicts etc. which is understood by older
			// versions of the Extension Manager:
		$typo3Version = '';
		$phpVersion = '';
		$dependenciesArr = array ();
		$conflictsArr = array ();

		if (is_array ($extensionInfoData->technicalData->dependencies)) {
			foreach ($extensionInfoData->technicalData->dependencies as $dependencyArr) {
				switch ($dependencyArr->extensionKey) {
					case 'typo3' :
						$typo3Version = $dependencyArr->versionRange;
					break;
					case 'php':
						$phpVersion = $dependencyArr->versionRange;
					break;
					default:
						if ($dependencyArr->kind == 'requires') {
							$dependenciesArr[] = $dependencyArr->extensionKey;
						} elseif ($dependencyArr->kind == 'requires') {
							$conflictsArr[] = $dependencyArr->extensionKey;
						}
				}
			}
		}

			// Prepare EM_CONF Array:
		$preparedEMConfArr = array(
			'title' => $extensionInfoData->metaData->title,
			'description' => $extensionInfoData->metaData->description,
			'category' => $extensionInfoData->metaData->category,
			'shy' => (is_string($extensionInfoData->technicalData->shy) ? ($extensionInfoData->technicalData->shy == 'false' ? 0 : 1) : (boolean)$extensionInfoData->technicalData->shy ? 1: 0),
			'version' => $extensionInfoData->version,
			'dependencies' => implode (',', $dependenciesArr),
			'conflicts' => implode (',', $conflictsArr),
			'priority' => $extensionInfoData->technicalData->priority,
			'loadOrder' => $extensionInfoData->technicalData->loadOrder,
			'TYPO3_version' => $typo3Version,
			'PHP_version' => $phpVersion,
			'module' => $extensionInfoData->technicalData->modules,
			'state' => $extensionInfoData->metaData->state,
			'uploadfolder' => (is_string($extensionInfoData->technicalData->uploadFolder) ? ($extensionInfoData->technicalData->uploadFolder == 'false' ? 0 : 1) : (boolean)$extensionInfoData->technicalData->uploadFolder ? 1: 0),
			'createDirs' => $extensionInfoData->technicalData->createDirs,
			'modify_tables' => $extensionInfoData->technicalData->modifyTables,
			'clearcacheonload' => (is_string($extensionInfoData->technicalData->clearCacheOnLoad) ? ($extensionInfoData->technicalData->clearCacheOnLoad == 'false' ? 0 : 1) : (boolean)$extensionInfoData->technicalData->clearCacheOnLoad ? 1: 0),
			'lockType' => $extensionInfoData->technicalData->lockType,
			'author' => $extensionInfoData->metaData->authorName,
			'author_email' => $extensionInfoData->metaData->authorEmail,
			'author_company' => $extensionInfoData->metaData->authorCompany,
			'CGLcompliance' => $extensionInfoData->infoData->codingGuidelineCompliance,
			'CGLcompliance_note' => $extensionInfoData->infoData->codeingGuidelineComplianceNote,
			'version' => $extensionInfoData->version,
		);

			// Compile T3X Data Array:
		$dataArr = array (
			'extKey' => $extensionInfoData->extensionKey,
			'EM_CONF' => $preparedEMConfArr,
			'misc' => array (),
			'techInfo' => array (),
			'FILES' => $preparedFilesDataArr
		);

		$t3xFileUncompressedData = serialize ($dataArr);
		$t3xFileData = md5 ($t3xFileUncompressedData) . ':gzcompress:'.gzcompress ($t3xFileUncompressedData);
		$gifFileData = $preparedFilesDataArr['ext_icon.gif']['content'];

			// Check if size of t3x file is too big:
		if (strlen ($t3xFileData) > $this->extensionMaxUploadSize) throw new SoapFault (TX_TER_ERROR_UPLOADEXTENSION_EXTENSIONTOOBIG, 'The extension size exceeded '.$this->extensionMaxUploadSize.' bytes which is the maximum for extension uploads.');

			// Create directories and build filenames:
		$firstLetter = strtolower (substr ($extensionInfoData->extensionKey, 0, 1));
		$secondLetter = strtolower (substr ($extensionInfoData->extensionKey, 1, 1));
		$fullPath = $this->parentObj->repositoryDir.$firstLetter.'/'.$secondLetter.'/';

		if (@!is_dir ($this->parentObj->repositoryDir . $firstLetter)) mkdir ($this->parentObj->repositoryDir . $firstLetter);
		if (@!is_dir ($this->parentObj->repositoryDir . $firstLetter . '/'. $secondLetter)) mkdir ($this->parentObj->repositoryDir . $firstLetter . '/' .$secondLetter);

		list ($majorVersion, $minorVersion, $devVersion) = t3lib_div::intExplode ('.', $extensionInfoData->version);
		$t3xFileName = strtolower ($extensionInfoData->extensionKey).'_'.$majorVersion.'.'.$minorVersion.'.'.$devVersion.'.t3x';
		$gifFileName = strtolower ($extensionInfoData->extensionKey).'_'.$majorVersion.'.'.$minorVersion.'.'.$devVersion.'.gif';

			// Write the files
		$fh = @fopen ($fullPath.$t3xFileName, 'wb');
		if (!$fh) throw new SoapFault (TX_TER_ERROR_UPLOADEXTENSION_WRITEERRORWHILEWRITINGFILES, 'Write error while writing .t3x file: '.$fullPath.$t3xFileName);
		fwrite ($fh, $t3xFileData);
		fclose ($fh);

		if (strlen ($gifFileData)) {
			$fh = @fopen ($fullPath.$gifFileName, 'wb');
			if (!$fh) throw new SoapFault (TX_TER_ERROR_UPLOADEXTENSION_WRITEERRORWHILEWRITINGFILES, 'Write error while writing .gif file: '.$fullPath.$gifFileName);
			fwrite ($fh, $gifFileData);
			fclose ($fh);
		}

			// Write some data back to $extensionInfoData:
		$extensionInfoData->t3xFileMD5 = md5 ($t3xFileData);
		$extensionInfoData->infoData->dataSize = strlen ($t3xFileUncompressedData);
		$extensionInfoData->infoData->dataSizeCompressed = strlen ($t3xFileData);
	}

	/**
	 * Extracts information from the extension information and stores it as a record in the database
	 * tables. These tables are used for quickly getting information about extensions for display
	 * in the TER frontend plugin.
	 *
	 * Note: All data written to the database (like everywhere else in the TER handling) is assumed to be
	 * utf-8 encoded!
	 *
	 * @param	object		$accountData: Username and passwords
	 * @param	object		$extensionInfoData: The general extension information as received by the SOAP server
	 * @param	array		$filesData: The array of file data objects as received by the SOAP server
	 * @return	void
	 * @access	protected
	 */
	protected function uploadExtension_writeExtensionInfoToDB ($accountData, $extensionInfoData, $filesData) {
			// Prepare information about files
		$extensionInfoData->technicalData->isManualIncluded = 0;
		foreach ($filesData->fileData as $fileData) {
			$extensionInfoData->infoData->files [$fileData->name] = array(
				'filename'      => $fileData->name,
				'size'          => $fileData->size,
				'mtime'         => $fileData->modificationTime,
				'is_executable' => $fileData->isExecutable,
			);
			if ($fileData->name === 'doc/manual.sxw') {
				$extensionInfoData->technicalData->isManualIncluded = 1;
			}
		}

			// Prepare the new records
		$extensionRow = array(
			'tstamp'           => $GLOBALS['SIM_EXEC_TIME'],
			'crdate'           => $GLOBALS['SIM_EXEC_TIME'],
			'pid'              => (int) $this->parentObj->extensionsPID,
			'extensionkey'     => $extensionInfoData->extensionKey,
			'version'          => $extensionInfoData->version,
			'title'            => $extensionInfoData->metaData->title,
			'description'      => $extensionInfoData->metaData->description,
			'state'            => $extensionInfoData->metaData->state,
			'category'         => $extensionInfoData->metaData->category,
			'ismanualincluded' => $extensionInfoData->technicalData->isManualIncluded,
			't3xfilemd5'       => $extensionInfoData->t3xFileMD5,
			'reviewstate'      => 0,
		);

			// Update an existing or insert a new extension record
		$table = 'tx_ter_extensions';
		$where = 'extensionkey = "' . $GLOBALS['TYPO3_DB']->quoteStr($extensionInfoData->extensionKey, $table) . '"
				  AND version  = "' . $GLOBALS['TYPO3_DB']->quoteStr($extensionInfoData->version, $table)      . '"';
		$extensionUid = $this->updateOrInsertRecord($table, $extensionRow, $where);

			// Get dependencies
		$dependenciesArr = array();
		if (is_array ($extensionInfoData->technicalData->dependencies)) {
			foreach ($extensionInfoData->technicalData->dependencies as $dependency) {
				$dependenciesArr[] = array(
					'kind'         => $dependency->kind,
					'extensionKey' => $dependency->extensionKey,
					'versionRange' => $dependency->versionRange,
				);
			}
		}

			// Cleanup some attributes
		$attributes = array('shy', 'uploadFolder', 'clearCacheOnLoad');
		foreach ($attributes as $attribute) {
			if (empty($extensionInfoData->technicalData->$attribute)
			 || $extensionInfoData->technicalData->$attribute === 'false') {
				$extensionInfoData->technicalData->$attribute = FALSE;
			} else {
				$extensionInfoData->technicalData->$attribute = TRUE;
			}
		}

			// Prepare details row
		$extensionDetailsRow = array (
			'pid'                            => (int) $this->parentObj->extensionsPID,
			'extensionuid'                   => (int) $extensionUid,
			'uploadcomment'                  => $extensionInfoData->infoData->uploadComment,
			'lastuploadbyusername'           => $accountData->username,
			'lastuploaddate'                 => $GLOBALS['SIM_EXEC_TIME'],
			'datasize'                       => $extensionInfoData->infoData->dataSize,
			'datasizecompressed'             => $extensionInfoData->infoData->dataSizeCompressed,
			'files'                          => serialize ($extensionInfoData->infoData->files),
			'codelines'                      => $extensionInfoData->infoData->codeLines,
			'codebytes'                      => $extensionInfoData->infoData->codeBytes,
			'techinfo'                       => serialize ($extensionInfoData->infoData->techInfo),
			'shy'                            => (int) $extensionInfoData->technicalData->shy,
			'dependencies'                   => serialize ($dependenciesArr),
			'createdirs'                     => $extensionInfoData->technicalData->createDirs,
			'priority'                       => $extensionInfoData->technicalData->priority,
			'modules'                        => $extensionInfoData->technicalData->modules,
			'uploadfolder'                   => (int) $extensionInfoData->technicalData->uploadFolder,
			'modifytables'                   => $extensionInfoData->technicalData->modifyTables,
			'clearcacheonload'               => (int) $extensionInfoData->technicalData->clearCacheOnLoad,
			'locktype'                       => $extensionInfoData->technicalData->lockType,
			'authorname'                     => $extensionInfoData->metaData->authorName,
			'authoremail'                    => $extensionInfoData->metaData->authorEmail,
			'authorcompany'                  => $extensionInfoData->metaData->authorCompany,
			'codingguidelinescompliance'     => $extensionInfoData->infoData->codingGuidelinesCompliance,
			'codingguidelinescompliancenote' => $extensionInfoData->infoData->codingGuidelinesComplianceNote,
			'loadorder'                      => $extensionInfoData->technicalData->loadOrder,
		);

			// Update an existing or insert a new extension details record
		$table = 'tx_ter_extensiondetails';
		$where = 'extensionuid = ' . (int) $extensionUid;
		$this->updateOrInsertRecord($table, $extensionDetailsRow, $where);
	}





	/*********************************************************
	 *
	 * deleteExtension helper functions
	 *
	 *********************************************************/

	/**
	 * Deletes an extension version from the database and its files from the repository directory.
	 * After the deletion, the extension index is updated.
	 *
	 * @param	string		$extensionKey: The extension key
	 * @param	string		$version: Version number of the extension to delete
	 * @return	void
	 * @access	protected
	 */
	protected function deleteExtension_deleteFromDBAndRemoveFiles ($extensionKey, $version) {
		global $TYPO3_DB;

		if (!@is_dir ($this->parentObj->repositoryDir)) throw new SoapFault (TX_TER_ERROR_GENERAL_EXTREPDIRDOESNTEXIST, 'Extension repository directory does not exist.');

		$result = $TYPO3_DB->exec_SELECTquery (
			'uid',
			'tx_ter_extensions',
			'extensionkey="'.$TYPO3_DB->quoteStr($extensionKey, 'tx_ter_extensions').'" AND version="'.$TYPO3_DB->quoteStr($version, 'tx_ter_extensions').'" AND pid='.intval($this->parentObj->extensionsPID)
		);
		if (!$result) {
			throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while selecting extension for deletion. (extensionkey: '.$extensionKey.' version: '.$version.')');
		}
		$extensionRow = $TYPO3_DB->sql_fetch_assoc($result);
		if (!intval($extensionRow['uid'])) {
			throw new SoapFault (TX_TER_ERROR_DELETEEXTENSION_EXTENSIONDOESNTEXIST, 'deleteExtension_deleteFromDBAndRemoveFiles: Extension does not exist. (extensionkey: '.$extensionKey.' version: '.$version.')');
		}

		$result = $TYPO3_DB->exec_DELETEquery (
			'tx_ter_extensiondetails',
			'extensionuid = '.intval($extensionRow['uid'])
		);
		$result = $TYPO3_DB->exec_DELETEquery (
			'tx_ter_extensions',
			'extensionkey="'.$TYPO3_DB->quoteStr($extensionKey, 'tx_ter_extensions').'" AND version="'.$TYPO3_DB->quoteStr($version, 'tx_ter_extensions').'"'
		);

		$firstLetter = strtolower (substr ($extensionKey, 0, 1));
		$secondLetter = strtolower (substr ($extensionKey, 1, 1));
		$fullPath = $this->parentObj->repositoryDir.$firstLetter.'/'.$secondLetter.'/';

		list ($majorVersion, $minorVersion, $devVersion) = t3lib_div::intExplode ('.', $version);
		$t3xFileName = strtolower ($extensionKey).'_'.$majorVersion.'.'.$minorVersion.'.'.$devVersion.'.t3x';
		$gifFileName = strtolower ($extensionKey).'_'.$majorVersion.'.'.$minorVersion.'.'.$devVersion.'.gif';

		@unlink ($fullPath.$t3xFileName);
		@unlink ($fullPath.$gifFileName);
	}





	/*********************************************************
	 *
	 * checkExtensionKey helper functions
	 *
	 *********************************************************/

	/**
	 * Checks if an extension key is formally valid
	 *
	 * @param	string		$extensionKey: The extension key to check
	 * @return	boolean		TRUE if the extension key is valid
	 * @access	protected
	 */
	protected function checkExtensionKey_extensionKeyIsFormallyValid ($extensionKey) {
		$ok = TRUE;

			// check for forbidden characters
		if (preg_match('/[^a-z0-9_]/', $extensionKey))	{
			$ok = FALSE;
		}

			// check for forbidden start and end characters
		if (preg_match('/^[0-9_]/', $extensionKey) || preg_match("/[_]$/", $extensionKey))	{
			$ok = FALSE;
		}

			// Length
		$extensionKeyLength = strlen(str_replace('_', '', $extensionKey));
		if ($extensionKeyLength < 3 || $extensionKeyLength > 30) {
			$ok = FALSE;
		}

			// Bad prefixes:
		$badPrefixesArr = array ('tx', 'user_', 'pages', 'tt_', 'sys_', 'ts_language_', 'csh_');
		foreach ($badPrefixesArr as $prefix) {
			if (t3lib_div::isFirstPartOfStr($extensionKey, $prefix))	{
				$ok = FALSE;
				break;
			}
		}

		return $ok;
	}





	/*********************************************************
	 *
	 * registerExtensionKey helper functions
	 *
	 *********************************************************/

	/**
	 * Writes extension key information into the database
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	object		$extensionKeyData: The extension key and other information
	 * @return	void
	 * @access	protected
	 */
	protected function registerExtensionKey_writeExtensionKeyInfoToDB ($accountData, $extensionKeyData) {
		global $TYPO3_DB, $TSFE;

		$extensionKeysRow = array(
			'pid' => $this->parentObj->extensionsPID,
			'tstamp' => time(),
			'crdate' => time(),
			'extensionkey' => $extensionKeyData->extensionKey,
			'title' => $TSFE->csConvObj->strtrunc('utf-8', $extensionKeyData->title, 50),
			'description' => $TSFE->csConvObj->strtrunc('utf-8', $extensionKeyData->description, 255),
			'ownerusername' => $accountData->username,
			'maxstoresize' => 0
		);
		$result = $TYPO3_DB->exec_INSERTquery ('tx_ter_extensionkeys', $extensionKeysRow);
		if (!$result) {
			throw new SoapFault (TX_TER_ERROR_REGISTEREXTENSIONKEY_DBERRORWHILEINSERTINGKEY, 'Database error while inserting extension key.');
		}
	}





	/*********************************************************
	 *
	 * modifyExtensionKey helper functions
	 *
	 *********************************************************/

	/**
	 * Writes modified extension key information into the database
	 *
	 * @param	object		$accountData: A valid username and password
	 * @param	object		$modifyExtensionKeyData: The extension key field which shall be updated
	 * @return	void
	 * @access	protected
	 */
	protected function modifyExtensionKey_writeModifiedKeyRecordIntoDB ($accountData, $modifyExtensionKeyData) {
		global $TYPO3_DB, $TSFE;

		$updateValues = array();

		if (isset ($modifyExtensionKeyData->ownerUsername)) {
			$modifyExtensionKeyData->ownerUsername;
			$res = $TYPO3_DB->exec_SELECTquery(
				'*',
				'fe_users',
				'username="'.$TYPO3_DB->quoteStr($modifyExtensionKeyData->ownerUsername, 'fe_users').'"'.$TSFE->sys_page->enableFields('fe_users')
			);
			if ($newOwnerUserRecordArr = $TYPO3_DB->sql_fetch_assoc($res)) {
				$updateValues['ownerusername'] = $newOwnerUserRecordArr['username'];
			} else return TX_TER_ERROR_GENERAL_USERNOTFOUND;
		}

		$res = $TYPO3_DB->exec_UPDATEquery (
			'tx_ter_extensionkeys',
			'extensionkey ="'.$TYPO3_DB->quoteStr($modifyExtensionKeyData->extensionKey, 'tx_ter_extensionkeys').'"',
			$updateValues
		);
		if (!$res) throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while updating extension key.');

		return TX_TER_RESULT_GENERAL_OK;
	}





	/*********************************************************
	 *
	 * setReviewState helper functions
	 *
	 *********************************************************/

	/**
	 * Writes modified review state into the database
	 *
	 * @param	object		$setReviewStateData: Extension key, version number and the new state
	 * @return	void
	 * @access	protected
	 */
	protected function setReviewState_writeNewStateIntoDB ($setReviewStateData) {
		global $TYPO3_DB, $TSFE;

		$res = $TYPO3_DB->exec_SELECTquery (
			'uid',
			'tx_ter_extensions',
			'extensionkey ="'.$TYPO3_DB->quoteStr($setReviewStateData->extensionKey, 'tx_ter_extensions').'" AND '.
				'version ="'.$TYPO3_DB->quoteStr($setReviewStateData->version, 'tx_ter_extensions').'"'
		);
		if (!$res) throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while searching for extension record.');
		if ($TYPO3_DB->sql_num_rows($res) != 1) throw new SoapFault (TX_TER_ERROR_SETREVIEWSTATE_EXTENSIONVERSIONDOESNOTEXIST, 'Extension version does not exist.');

		$res = $TYPO3_DB->exec_UPDATEquery (
			'tx_ter_extensions',
			'extensionkey ="'.$TYPO3_DB->quoteStr($setReviewStateData->extensionKey, 'tx_ter_extensions').'" AND '.
				'version ="'.$TYPO3_DB->quoteStr($setReviewStateData->version, 'tx_ter_extensions').'"',
			array('reviewstate' => intval($setReviewStateData->reviewState))
		);
		if (!$res) throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while updating extension review state.');
	}





	/*********************************************************
	 *
	 * increaseExtensionDownloadCounter helper functions
	 *
	 *********************************************************/

	/**
	 * Reads, increases and writes the download counter of an extension version
	 * into the database.
	 *
	 * @param	object		$extensionVersionDataAndIncrementor: Extension key and version number and the download count incrementor
	 * @return	void
	 * @access	protected
	 */
	protected function increaseExtensionDownloadCounter_increaseCounterInDB ($extensionVersionDataAndIncrementor) {
		global $TYPO3_DB, $TSFE;

		$res = $TYPO3_DB->exec_SELECTquery (
			'uid, downloadcounter',
			'tx_ter_extensions',
			'extensionkey ="'.$TYPO3_DB->quoteStr($extensionVersionDataAndIncrementor->extensionKey, 'tx_ter_extensions').'" AND '.
				'version ="'.$TYPO3_DB->quoteStr($extensionVersionDataAndIncrementor->version, 'tx_ter_extensions').'"'
		);
		if (!$res) throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while searching for extension record.');
		if ($TYPO3_DB->sql_num_rows($res) != 1) throw new SoapFault (TX_TER_ERROR_INCREASEEXTENSIONDOWNLOADCOUNTER_EXTENSIONVERSIONDOESNOTEXIST, 'Extension version does not exist.');

		$currentRow = $TYPO3_DB->sql_fetch_assoc ($res);
		$incrementor = (integer)($extensionVersionDataAndIncrementor->downloadCountIncrementor);
		if ($incrementor !== abs($incrementor)) throw new SoapFault (TX_TER_ERROR_INCREASEEXTENSIONDOWNLOADCOUNTER_INCREMENTORNOTPOSITIVEINTEGER, 'The incrementor must be a positive integer value.');
		$newCounter = intval($currentRow['downloadcounter']) + $incrementor;

		$res = $TYPO3_DB->exec_UPDATEquery (
			'tx_ter_extensions',
			'uid='.$currentRow['uid'],
			array('downloadcounter' => $newCounter)
		);
		if (!$res) throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while updating extension download counter.');

		$res = $TYPO3_DB->exec_SELECTquery (
			'uid, downloadcounter',
			'tx_ter_extensionkeys',
			'extensionkey ="'.$TYPO3_DB->quoteStr($extensionVersionDataAndIncrementor->extensionKey, 'tx_ter_extensions').'"'
		);
		if (!$res) throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while searching for extension key record.');
		if ($TYPO3_DB->sql_num_rows($res) != 1) throw new SoapFault (TX_TER_ERROR_INCREASEEXTENSIONDOWNLOADCOUNTER_EXTENSIONKEYDOESNOTEXIST, 'Extension key does not exist.');

		$currentRow = $TYPO3_DB->sql_fetch_assoc ($res);
		$newCounter = intval($currentRow['downloadcounter']) + $incrementor;

		$res = $TYPO3_DB->exec_UPDATEquery (
			'tx_ter_extensionkeys',
			'uid='.$currentRow['uid'],
			array('downloadcounter' => $newCounter)
		);
		if (!$res) throw new SoapFault (TX_TER_ERROR_GENERAL_DATABASEERROR, 'Database error while updating extension total download counter.');
	}


	/**
	 * Update an existing or create a new database record
	 * 
	 * @param string $table Table name
	 * @param array $recordData Record key <-> value pairs
	 * @param array $where Where statement
	 * @return integer UID of the new or updated record
	 */
	public function updateOrInsertRecord($table, array $recordData, $where = '') {
		if (empty($table) || empty($recordData)) {
			return FALSE;
		}

			// Load environment
		if (empty($this->tce)) {
			$this->helperObj->loadBackendUser(1, '_ter_', TRUE);
			$GLOBALS['TSFE']->includeTCA();
			$this->helperObj->loadLang();
			$this->loadTceForm();
		}

			// Check if the record already exists and get UID if not given
		$update = FALSE;
		if (!empty($where)) {
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('uid', $table, $where);
			if (!empty($result['uid'])) {
				$update = TRUE;
				if (empty($recordData['uid'])) {
					$recordData['uid'] = $result['uid'];
				}
			}
		}

			// Get key name
		$key = ($update ? (int) $recordData['uid'] : 'NEW' . uniqid());
		unset($recordData['uid']);

			// Data map for TCEMAIN record
		$data = array(
			$table => array(
				$key => $recordData,
			),
		);

			// Finally update or insert record
		$this->tce->datamap = $data;
		$this->tce->process_datamap();

			// Remap NEW key to real UID
		if (!empty($this->tce->substNEWwithIDs[$key])) {
			$key = $this->tce->substNEWwithIDs[$key];
		}

		return (int) $key;
	}


	/**
	 * Load an instance of the TCEMAIN object
	 * 
	 * @return void
	 */
	public function loadTceForm() {
		$this->tce = t3lib_div::makeInstance('t3lib_TCEmain');
		$this->tce->stripslashes_values = 0;
		$this->tce->workspace = 0;
		$this->tce->bypassWorkspaceRestrictions = TRUE;
		$this->tce->start(array(), array());
	}

}
?>