<?php
	//IMathAS (c) 2006 David Lippman
	//Filter file - post-processes generated question HTML: normalizes embeds,
	//expands [WA]/[EMBED]/[CDF] shortcodes, and (in fallback display modes)
	//converts ASCIImath/ASCIIsvg to text or images.

	//load in filters as needed
	$filterdir = rtrim(dirname(__FILE__), '/\\');
	//require_once "$filterdir/simplelti/simplelti.php";
	if ((isset($_SESSION['mathdisp']) && $_SESSION['mathdisp']==2 ) || isset($loadmathfilter)) { //use image fallback for math
		require_once "$filterdir/math/ASCIIMath2TeX.php";
		$AMT = new AMtoTeX;
	}
	// Server-side graph rasterization (asciisvgimg) removed: this engine renders
	// graphs client-side (graphdisp=1) and never uses the image fallback.
	if ((!isset($_SESSION['graphdisp']) || $_SESSION['graphdisp']==0)) {
		require_once "$filterdir/graph/sscrtotext.php";
	}
	function mathfiltercallback($arr) {
		global $AMT,$mathimgurl,$coursetheme;
		//$arr[1] = str_replace(array('&ne;','&quot;','&lt;','&gt;','&le;','&ge;'),array('ne','"','lt','gt','le','ge'),$arr[1]);
		$arr[1] = str_replace(array('&ne;','&quot;','&le;','&ge;','<','>'),array('ne','"','le','ge','&lt;','&gt;'),$arr[1]);
		$tex = $AMT->convert($arr[1]);
		if (trim($tex)=='') {
			return '';
		} else {
			if (isset($coursetheme) && strpos($coursetheme,'_dark')!==false) {
				$tex = '\\reverse '.$tex;
			}
			if (!empty($GLOBALS['texdisp'])) {
				if (isset($GLOBALS['texdoubleescape'])) {
					return ' \\\\('.htmlentities($tex).'\\\\) ';
				} else {
					return ' '.htmlentities($tex).' ';
				}
			} else {
				return ('<img style="vertical-align: middle;" src="'.$mathimgurl.'?'.rawurlencode($tex).'" alt="'.str_replace('"','&quot;',$arr[1]).'">');
			}
		}
	}
	function svgsscrtotextcallback($arr) {
		if (trim($arr[2])=='') {return '';}
		return '['.shortscriptToText($arr[2]).']';
	}
	function filter($str) {
		global $userfullname,$urlmode,$imasroot;
		if ($urlmode == 'https://') {
			$str = str_replace(array('http://www.youtube.com','http://youtu.be'),array('https://www.youtube.com','https://youtu.be'), $str);
		}
        $str = str_replace('"http://quietube.com/v.php/http','"http', $str);
		if (strip_tags($str)==$str) {
			$str = str_replace("\n","<br/>\n",$str);
		}
		$str = str_replace('alt="decorative"', 'alt="" role="presentation"', $str);
		if ($_SESSION['graphdisp']==0) {
			if (strpos($str,'embed')!==FALSE) {
				$str = preg_replace('/<embed[^>]*alt="([^"]*)"[^>]*>/',"[$1]", $str);
				//$str = preg_replace('/<embed[^>]*sscr[^>]*>/',"[Graph with no description]", $str);
				$str = preg_replace_callback('/<\s*embed[^>]*?sscr=(.)(.+?)\1.*?>/s','svgsscrtotextcallback',$str);
			}
            $str = preg_replace('/<canvas[^>]*aria-label="([^"]*)"[^>]*>.*?<\/canvas>/',"[$1]", $str);
		}
		if ($_SESSION['mathdisp']==2) {
			$str = str_replace('\\`','&grave;',$str);
			if (strpos($str,'`')!==FALSE) {
				$str = preg_replace_callback('/`(.*?)`/s', 'mathfiltercallback', $str);
			}
			$str = str_replace('&grave;','`',$str);
		}
		$str = str_replace("<embed type='image/svg+xml'","<embed type='image/svg+xml' wmode=\"transparent\" ",$str);
		$str = str_replace("src=\"$imasroot/javascript/d.svg\"","",$str);

		if (strpos($str,'[WA')!==false) {
			$search = '/\[WA:\s*(.+?)\s*\]/';

			if (preg_match_all($search, $str, $res, PREG_SET_ORDER)){
				foreach ($res as $resval) {
					$tag = '<script type="text/javascript" id="WolframAlphaScript'.$resval[1].'" src="'.$urlmode.'//www.wolframalpha.com/widget/widget.jsp?id='.$resval[1].'"></script>';
					$str = str_replace($resval[0], $tag, $str);
				}
			}
		}

		if (strpos($str,'[EMBED')!==false) {
			$search = '/\[EMBED:\s*([^\]]+)\]/';
            $zindex = 50;
			if (preg_match_all($search, $str, $res, PREG_SET_ORDER)){
				foreach ($res as $resval) {
                    $respt = explode(',',$resval[1]);
                    
                    if (substr($respt[0],0,3)=='QID') {
                        $url = implode('&',$respt);
                        $w = '100%';
                        $h = '200';
                        $qs = preg_replace('/[^\w=&]/','', trim(substr($url,3)));
                        $uniqid = uniqid('eq2');
                        $url = $GLOBALS['basesiteurl'] . '/embedq2.php?frame_id='.$uniqid.'&id='.$qs;
                        $tag = '<div id="'.$uniqid.'wrap" class="embedwrap">';
                        $tag .= "<iframe id=\"$uniqid\" width=\"$w\" height=\"$h\" src=\"$url\" style=\"z-index:$zindex\" frameborder=\"0\">";
                        $tag .= '</iframe></div>';
                        //$str = str_replace($resval[0], $tag, $str);
                        $str = substr_replace($str, $tag, strpos($str, $resval[0]), strlen($resval[0]));
                        $zindex--;
                        continue;
                    }

					if (isset($respt[3])) {
						$nobord = true;
						array_pop($respt);
					} else {
						$nobord = false;
					}
					if (count($respt)==1) {
						$url = $respt[0]; $w = 600; $h = 400;
					} else if (count($respt)<3) {
						continue;
					} else {
						if (strpos($respt[2],'http')!==false) {
							list ($w,$h,$url) = $respt;
						} else {
							list ($url,$w,$h) = $respt;
						}
					}
                    $url = trim(str_replace(array('"','&nbsp;'),'',$url));
                    if (substr($url,0,18)=='https://tegr.it/y/') {
						$url = preg_replace('/[^\w:\/\.]/','',$url);
						//$tag = '<script type="text/javascript" src="'.$url.'"></script>';
						$url = "$imasroot/course/embedhelper.php?w=$w&amp;h=$h&amp;type=tegrity&amp;url=".Sanitize::encodeUrlParam($url);
						$tag = "<iframe width=\"$w\" height=\"$h\" src=\"$url\" frameborder=\"0\"></iframe>";

					} else {
						$tag = "<iframe width=\"$w\" height=\"$h\" src=\"$url\" ";
						if ($nobord) {
							$tag .= 'frameborder="0" ';
						}
						$tag .= "></iframe>";
					}
					$str = str_replace($resval[0], $tag, $str);
				}
			}
		}

		if (strpos($str,'[CDF')!==false) {
			$search = '/\[CDF:\s*([^,]+),([^,]+),([^,\]]+)\]/';

			if (preg_match_all($search, $str, $res, PREG_SET_ORDER)){
				foreach ($res as $resval) {
					/*if (!isset($GLOBALS['has_set_cdf_embed_script'])) {
						$GLOBALS['has_set_cdf_embed_script'] = true;
						$tag = '<script type="text/javascript" src="'.$urlmode.'www.wolfram.com/cdf-player/plugin/v2.1/cdfplugin.js"></script><script type="text/javascript">var cdf = new cdfplugin();';
					} else {
						$tag = '<script type="text/javascript">';
					}
					if (strpos($resval[3],'http')!==false) {
						list ($junk,$w,$h,$url) = $resval;
					} else {
						list ($junk,$url,$w,$h) = $resval;
					}

					$tag .= "cdf.embed('$url',$w,$h);</script>";
					$str = str_replace($resval[0], $tag, $str);
					*/
					if (strpos($resval[3],'http')!==false) {
						list ($junk,$w,$h,$url) = $resval;
					} else {
						list ($junk,$url,$w,$h) = $resval;
					}
					$url = "$imasroot/course/embedhelper.php?w=$w&amp;h=$h&amp;type=cdf&amp;url=".Sanitize::encodeUrlParam($url);
					$tag = "<iframe width=\"$w\" height=\"$h\" src=\"$url\" frameborder=\"0\"></iframe>";
					$str = str_replace($resval[0], $tag, $str);
				}
			}
		}
		return $str;
	}
?>
