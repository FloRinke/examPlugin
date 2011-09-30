<?php

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_klausuren_upload extends DokuWiki_Action_Plugin {

	/**
	 * return some info
	 */
	function getInfo(){
		return array(
			'author' => 'Tim Roes',
			'email'  => 'mail@timroes.de',
			'date'   => '2011-07-30',
			'name'   => 'Upload Action',
			'desc'   => 'This component is repsonsible for the upload of files.',
			'url'    => 'http://www.hska.info'
		);
	}

	/**
	 * Register its handlers with the dokuwiki's event controller
	 */
	function register(&$controller) {
		$controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this,
			'files_uploaded');
	}

	/**
	 * This method checks if the user sent some file to the server.
	 * If (s)he did so, valid all postet data, and move the uploaded file.
	 */
	function files_uploaded(&$event, $param) {

		$NS = $this->getConf('unterlagenNS').'/'.$_POST['lesson'].'/';
		$NS = cleanID($NS);

		if(!$_FILES['upload'])
			return;

		// check authes
		$AUTH = auth_quickaclcheck("$NS:*");
		if($AUTH < AUTH_UPLOAD) {
			msg("Keine Rechte die Datei hochzuladen.", -1);
			return;
		}

		// Check if post data is valid
		if(!in_array($_POST['type'], array('klausur','loesung'))
			|| !preg_match('/^\d{4}(ws|ss)$/', $_POST['semester'])
			|| !preg_match('/^\w+$/', $_POST['lesson'])) {
			msg("Fehler im System. Dateiupload fehlgeschlagen.", -1);
			return;
		}

		// Check if klausur exists if solution is uploaded
		if($_POST['type'] == "loesung") {
			$helper =& plugin_load('helper', 'klausuren_helper');
			$exists = $helper->getKlausurStatus($_POST['semester'], $_POST['lesson']);
			if(!$exists['klausur']) {
				msg("Bitte zunächst die Klausur von dem entsprechenden Semester hochladen.", -1);
				return;
			}
		}

		// Check if filetype is pdf
		if(mime_content_type($_FILES['upload']['tmp_name']) != 'application/pdf') {
			msg("Die Datei muss im PDF Format vorliegen. Falls du sie nicht konvertieren kannst, maile uns die Datei bitte.", -1);
			return;
		}

		// handle upload
		if($_FILES['upload']['tmp_name']){
			$_POST['id'] = $_POST['lesson'].'_'.$_POST['semester'].'_'.$_POST['type'].'.pdf';
			$JUMPTO = media_upload($NS,$AUTH);
		    if($JUMPTO) {
				$NS = getNS($JUMPTO);
				$ID = $_POST['page'];
				$NS = getNS($ID);
			}
		}	

	}

}

