<?php
/**
 * @file classes/security/authorization/SwordServerAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SwordServerAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to that makes sure that a user is logged in.
 */

use \Firebase\JWT\JWT;

class SwordServerAccessPolicy extends AuthorizationPolicy {

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function __construct($request) {
		$this->request = $request;
	}

	/**
	 * Serve a SWORD Error Document to unauthorized requests
	 */
	function unauthorizedResponse() {
		$swordError = new SwordError([
			'summary' => "You are not authorized to make this request"
		]);
		header('Content-Type: application/xml');
		header("HTTP/1.1 401 Unauthorized");

		echo $swordError->saveXML();
		exit;
	}

	/**
	 * @copydoc AuthorizationPolicy::effect()
	 */
	function effect() {
		$callOnDeny = array($this, 'unauthorizedResponse', array());
		$this->setAdvice(AUTHORIZATION_ADVICE_CALL_ON_DENY, $callOnDeny);
		$headers = getallheaders();
		$user = null;
		// 1. Try Http Basic Auth
		if (array_key_exists('Authorization', $headers)) {
			$auth_header = $headers["Authorization"];
			$userPass = base64_decode(substr($auth_header, 6));
			$userPass = explode(":", $userPass);
			if (Validation::checkCredentials($userPass[0], $userPass[1])) {
				$userDao = DAORegistry::getDAO('UserDAO');
				$user = $userDao->getByUsername($userPass[0]);
			}
		}
		// 2. Try API Key
		if (!$user && $apiToken = $headers['X-Ojs-Sword-Api-Token']) {
				$secret = Config::getVar('security', 'api_key_secret', '');
			try {
				$decoded = json_decode(JWT::decode($apiToken, $secret, array('HS256')));
				$userDao = DAORegistry::getDAO('UserDAO');
				$user = $userDao->getBySetting('apiKey', $decoded);
			} catch (Firebase\JWT\SignatureInvalidException $e) {
			}
		}
		
		if ($user && $user->hasRole(ROLE_ID_MANAGER, $this->request->getJournal()->getId())) {
			$this->addAuthorizedContextObject(ASSOC_TYPE_USER, $user);
			return AUTHORIZATION_PERMIT;
		}
		return AUTHORIZATION_DENY;
	}
}
