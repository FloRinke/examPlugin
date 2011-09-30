<?php
/**
 * This is a modified dw2Pdf plugin, to export the solution pages to pdf.
 * It takes an id of a specific pages and converts it to a string of the pdf file.
 *
 * dw2Pdf Plugin: Conversion from dokuwiki content to pdf.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class PdfExport {

	static function convert($filename, $title) {
		global $REV;
		global $conf;

		// check user's rights
		if ( auth_quickaclcheck($filename) < AUTH_READ ) return false;

		// initialize PDF library
		require_once(dirname(__FILE__)."/DokuPDF.class.php");
		$mpdf = new DokuPDF();

		$mpdf->debug = true;
		$mpdf->showImageErrors = true;

		// let mpdf fix local links
		$self = parse_url(DOKU_URL);
		$url  = $self['scheme'].'://'.$self['host'];
		if($self['port']) $url .= ':'.$port;
		$mpdf->setBasePath($url);

		// some default settings
		$mpdf->mirrorMargins          = 0;  // Use different Odd/Even headers and footers and mirror margins
		$mpdf->defaultheaderfontsize  = 8;  // in pts
		$mpdf->defaultheaderfontstyle = ''; // blank, B, I, or BI
		$mpdf->defaultheaderline      = 1;  // 1 to include line below header/above footer
		$mpdf->defaultfooterfontsize  = 8;  // in pts
		$mpdf->defaultfooterfontstyle = ''; // blank, B, I, or BI
		$mpdf->defaultfooterline      = 1;  // 1 to include line below header/above footer

		// prepare HTML header styles
		$html  = '<html><head>';
		$html .= '<style>';
		//$html .= file_get_contents('./conf/style.css');
		//$html .= @file_get_contents('./conf/style.local.css');
		$html .= '</style>';
		$html .= '</head><body>';

		// set headers/footers
		self::prepare_headers($mpdf, $title);

		$html .= p_wiki_xhtml($filename,$REV,false);

		// Add footer information box
		$html .= self::citation($filename);

		self::arrangeHtml($html, 'span,acronym');
		$mpdf->WriteHTML($html);

		$title = $_GET['pdfbook_title'];
		$output = 'S';
		return $mpdf->Output(urlencode($title).'.pdf', $output);

		exit();
	}

	/**
	 * Setup the page headers and footers
	 */
	protected static function prepare_headers(&$mpdf, $title){
		global $ID;
		global $REV;
		global $conf;

		// prepare replacements
		$replace = array(
			'@ID@'      => $ID,
			'@PAGE@'    => '{PAGENO}',
			'@PAGES@'   => '{nb}',
			'@TITLE@'   => strtoupper($title),
			'@WIKI@'    => $conf['title'],
			'@WIKIURL@' => DOKU_URL,
			'@UPDATE@'  => dformat(filemtime(wikiFN($ID,$REV))),
			'@PAGEURL@' => wl($ID,($REV)?array('rev'=>$REV):false, true, "&"),
			'@DATE@'    => dformat(time()),
		);

		// do the replacements
		$fo = str_replace(array_keys($replace), array_values($replace), '@WIKIURL@ || Exported on @DATE@');
		//$fe = str_replace(array_keys($replace), array_values($replace), $this->getConf("footer_even"));
		$ho = str_replace(array_keys($replace), array_values($replace), '@TITLE@ || @PAGE@/@PAGES@');
		//$he = str_replace(array_keys($replace), array_values($replace), $this->getConf("header_even"));

		// set the headers/footers
		$mpdf->SetHeader($ho);
		//$mpdf->SetHeader($he, 'E');
		$mpdf->SetFooter($fo);
		//$mpdf->SetFooter($fe, 'E');

		// title
		$mpdf->SetTitle(strtoupper($title));
	}

	/**
	 * Fix up the HTML a bit
	 *
	 * FIXME This is far from perfect and will modify things within code and
	 * nowiki blocks. It would probably be a good idea to use a real HTML
	 * parser or our own renderer instead of modifying the HTML at all.
	 */
	protected static function arrangeHtml(&$html, $norendertags = '' ) {
		// add bookmark links
		$bmlevel = 5;
		if($bmlevel > 0) {
			$html = preg_replace("/\<a name=(.+?)\>(.+?)\<\/a\>/s",'$2',$html);
			for ($j = 1; $j<=$bmlevel; $j++) {
				$html = preg_replace("/\<h".$j."\>(.+?)\<\/h".$j."\>/s",'<h'.$j.'>$1<bookmark content="$1" level="'.($j-1).'"/></h'.$j.'>',$html);
			}
		}

		// insert a pagebreak for support of WRAP and PAGEBREAK plugins
		$html = str_replace('<br style="page-break-after:always;">','<pagebreak />',$html);
		$html = str_replace('<div class="wrap_pagebreak"></div>','<pagebreak />',$html);
		$html = str_replace('<span class="wrap_pagebreak"></span>','<pagebreak />',$html);

		// Customized to strip all span tags so that the wiki <code> SQL would display properly
		$norender = explode(',',$norendertags);
		self::strip_only($html, $norender);
	}

	/**
	 * Create the citation box
	 *
	 * @todo can we drop the inline style here?
	 */
	protected static function citation($page) {
		global $conf;

		$html  = '';
		$html .= "<br><br><div style='font-size: 80%; border: solid 0.5mm #DDDDDD;background-color: #EEEEEE; padding: 2mm; border-radius: 2mm 2mm; width: 100%;'>";
		$html .= "Aus dem Wiki von:<br>";
		$html .= "<a href='".DOKU_URL."'>".DOKU_URL."</a>&nbsp;-&nbsp;"."<b>".$conf['title']."</b>";
		$html .= "<br><br>Link zur Lösung: (Bitte helft mit sie zu verbessern!)<br>";
		$html .= "<b><a href='".wl($page, false, true, "&")."'>".wl($page, false, true, "&")."</a></b>";
		$html .= "</div>";
		return $html;
	}

	/**
	 * Strip unwanted tags
	 *
	 * @fixme could this be done by strip_tags?
	 * @author Jared Ong
	 */
	protected static function strip_only(&$str, $tags) {
		if(!is_array($tags)) {
			$tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : array($tags));
			if(end($tags) == '') array_pop($tags);
		}
		foreach($tags as $tag) $str = preg_replace('#</?'.$tag.'[^>]*>#is', '', $str);
	}
}
