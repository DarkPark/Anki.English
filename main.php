<?php
/**
 * Main html generating file
 * @author DarkPark, Urkaine Odessa 2011
 */

set_time_limit(0);

date_default_timezone_set('Europe/Kiev');
//header('Content-Type: text/plain; charset=utf-8');

include 'sqlite.php';

define('file_input',  'input.txt');
define('file_output', 'output.txt');
define('file_words',  'words.txt');
define('file_missed', 'missed.txt');

// Create the stream context
$context = stream_context_create(array(
	'http' => array(
		'timeout' => 10	// timeout in seconds
	)
));

// db connection
try {
	$dbh = new PDO('sqlite:db/english.sqlite');
	$dbh->beginTransaction();
} catch ( PDOException $e ) {
	die('Connection failed: '.$e->getMessage());
}

function GetWordID ( $word ) {
	if ( ($data = db_query(sprintf("select id from words where word = '%s' limit 1", db_escape($word)))) ) {
		$data = $data->fetch(PDO::FETCH_ASSOC);
		return $data['id'];
	}
}

function SaveSound ( & $jdata ) {
	global $context;
	if ( $jdata && isset($jdata['primaries']) ) {
		$data = array();
		foreach ( $jdata['primaries'] as $primary ) {
			if ( isset($primary['terms']) ) {
				foreach ( $primary['terms'] as $term ) {
					if ( isset($term['type']) && trim($term['type']) == 'sound' ) {
						$mp3 = file_get_contents($term['text'], 0, $context);
						if ( $mp3 ) {
							$data[hash('md5', $mp3)] = $mp3;
						}
					}
				}
			}
		}
		$i = 0;
		if ( $data ) {
			if ( count($data) == 1 ) {
				file_put_contents('audio' . DIRECTORY_SEPARATOR . $jdata['query'] . ".mp3" , $mp3);
			}
			else if ( count($data) > 1 ) {
				foreach ( $data as $mp3 ) {
					file_put_contents('audio' . DIRECTORY_SEPARATOR . $jdata['query'] . "@$i.mp3" , $mp3);
					$i++;
				}
			}
		}
	}
}

function GetSoundList ( $word ) {
	$list = array();
	if ( $word ) {
		if ( is_file('audio' . DIRECTORY_SEPARATOR . "$word.mp3") ) {
			$list[] = "[sound:$word.mp3]";
		} else {
			for ( $index = 0; $index < 10; $index++) {
				if ( is_file('audio' . DIRECTORY_SEPARATOR . "$word@$index.mp3") ) {
					$list[] = "[sound:$word@$index.mp3]";
				}
			}
		}
	}
	return $list;
}

function json_error () {
	switch (json_last_error()) {
        case JSON_ERROR_NONE:
            //echo $word, ' - No errors';
        break;
        case JSON_ERROR_DEPTH:
            return "Maximum stack depth exceeded\n";
        break;
        case JSON_ERROR_STATE_MISMATCH:
            return "Underflow or the modes mismatch\n";
        break;
        case JSON_ERROR_CTRL_CHAR:
            return "Unexpected control character found\n";
        break;
        case JSON_ERROR_SYNTAX:
            return "Syntax error, malformed JSON\n";
        break;
        case JSON_ERROR_UTF8:
            return "Malformed UTF-8 characters, possibly incorrectly encoded\n";
        break;
        default:
            return "Unknown error\n";
        break;
    }
}

function PrepareTranslation ( $data ) {
	$result = '';
	if ( $data ) {
		$data = json_decode($data, true);
		if ( $data && isset($data['primaries']) ) {
			foreach ( $data['primaries'] as $primary ) {
				$result = $result . ($result ? '<br>' : '');
				$transcription = '';
				foreach ( $primary['terms'] as $term ) {
					if ( $term['type'] == 'phonetic' ) {
						$transcription = $term['text'];
					}
				}
				$result .= $transcription;
				foreach ( $primary['entries'] as $entry ) {
					$labels = array();
					$meanings = array();
					foreach ( $entry['labels'] as $label ) {
						$labels[] = trim($label['text']);
					}
					foreach ( $entry['entries'] as $meaning ) {
						if ( $meaning['type'] == 'meaning' ) {
							foreach ( $meaning['terms'] as $term ) {
								$meanings[] = trim($term['text']);
							}
						}
					}
					$result .= '<br><b>' . implode(', ', $labels) . '</b>: ' . implode(', ', $meanings);
				}
				//$result .= '<br>';
			}
		}
	}
	return $result;
}

function GenerateFacts () {
	$items = db_array(db_query('select word, translation from words where translation <> ""'));
	if ( $items ) {
		$lines = '';
		foreach ( $items as $item ) {
			$sounds = GetSoundList($item['word']);
			$trans  = PrepareTranslation($item['translation']);
			$lines .= $item['word'] . "\t" . $trans . "\t" . implode('', $sounds) . "\n";
		}
		//echo $lines;
		file_put_contents(file_output, $lines);
	}
}

function AddWord ( & $word_list, $word ) {
	$word = trim($word, "\x00..\x1F"); // trim the ASCII control characters
	$word = trim($word, '~!@#$%^&*()_+{}[]\\|<>.,?/";%:()-=\''); // trim the ASCII control characters
	
	if ( $word ) {
		if ( isset($word_list[$word]) ) {
			$word_list[$word]++;
		} else {
			$word_list[$word] = 1;
		}
	}
}

$file_input = file_get_contents(file_input);
$word_list  = array();
foreach ( str_word_count(strtolower($file_input), 1) as $word ) {
	$word = trim($word, "\x00..\x1F"); // trim the ASCII control characters
	$word = trim($word, '~!@#$%^&*()_+{}[]\\|<>.,?/";%:()-=\''); // trim the ASCII control characters
	if ( $word ) {
		AddWord($word_list, $word);
		
		if ( strpos($word, '-') !== false ) {
			AddWord($word_list, str_replace('-', '', $word));
			foreach ( explode('-', $word) as $wval) {
				AddWord($word_list, $wval);
			}
		}
		
		
		if ( substr($word, -1) == 's' ) {
			AddWord($word_list, substr($word, 0, -1));
		}
		if ( substr($word, -2) == 'es' ) {
			AddWord($word_list, substr($word, 0, -2));
		}
		if ( substr($word, -3) == 'ies' ) {
			AddWord($word_list, substr($word, 0, -3) . 'y');
		}
		if ( substr($word, -3) == 'ing' ) {
			AddWord($word_list, substr($word, 0, -3));
		}
		if ( substr($word, -2) == 'ed' ) {
			AddWord($word_list, substr($word, 0, -2));
		}
	}
}

$word_list_sorted = array();
foreach ( $word_list as $word => $word_count ) {
	$word_list_sorted[$word_count][] = $word;
}
krsort($word_list_sorted);

file_put_contents(file_words, print_r($word_list, true));
//file_put_contents(file_output, print_r($word_list_sorted, true));

file_put_contents(file_missed, "\n" . date('Y.m.d H:i:s') . "\n", FILE_APPEND);
$utime = time();

function GetWordData ( $word ) {
	global $context;
	
	$query = '';
	
	try {
		$query = file_get_contents("http://www.google.com/dictionary/json?callback=dict_api.callbacks.id100&q=$word&sl=en&tl=ru&restrict=pr&client=te", 0, $context);
	} catch (Exception $e) {
		echo "\nexception: ",  $e->getMessage(), "\n";
		sleep(1);
		try {
			$query = file_get_contents("http://www.google.com/dictionary/json?callback=dict_api.callbacks.id100&q=$word&sl=en&tl=ru&restrict=pr&client=te", 0, $context);
		} catch (Exception $e) {
			echo "\nexception again: ",  $e->getMessage(), "\n";
		}
	}
	
	if ( $query ) {
		$query = str_replace(array('dict_api.callbacks.id100(', ',200,null)'), '', $query);
		$query = str_replace('\x26', "&", $query);
		$query = str_replace('\x27', "'", $query);
		$query = html_entity_decode($query, ENT_QUOTES);
	}
	return $query;
}

/*$jdata = json_decode($query, true);
echo($query);
echo(json_error($word));
print_r($jdata);
exit;/**/

foreach ( $word_list_sorted as $wcount => $wlist ) {
	if ( $wcount ) {
		foreach ( $wlist as $word ) {
			$id_word = GetWordID($word);
			//$id_word = null;
			if ( !$id_word ) {
				$query = GetWordData($word);
				if ( $query ) {
					$jdata = json_decode($query, true);
					$error = json_error($word);
					//file_put_contents(file_output, "\n" . $query . "\n", FILE_APPEND);
					if ( isset($jdata['primaries']) ) {
						echo "$word ";
						//file_put_contents(file_output, print_r($jdata, true) . "\n**********************************\n", FILE_APPEND);
						$translation = $query;
						$sql = sprintf("insert into words (word, translation, time) values ('%s', '%s', %s)", db_escape($word), db_escape($translation), $utime);
						db_insert($sql);
						SaveSound($jdata);
					} else {
						echo "[$word] ";
						file_put_contents(file_missed, $word . "\n", FILE_APPEND);
						if ( $error ) {
							echo " - $error ";
							file_put_contents(file_missed, $error . "\n", FILE_APPEND);
							file_put_contents(file_missed, $query . "\n", FILE_APPEND);
						}
						db_insert(sprintf("insert into words (word, translation, time) values ('%s', '%s', %s)", db_escape($word), '', $utime));
					}
				}
			}
		}
	}
}

//$query = file_get_contents('http://www.google.com/dictionary/json?callback=dict_api.callbacks.id100&q=don't&sl=en&tl=ru&restrict=pr,de&client=te');
//$query = str_replace(array('dict_api.callbacks.id100(', ',200,null)'), '', $query);
//echo($query);

GenerateFacts();

$dbh->commit();

?>
