<?php
header('Content-Type: text/html; charset=UTF-8');
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}


function charactersecurity_info()
{
    return array(
        "name"			=> "Charaktersicherung",
        "description"	=> "Ermöglicht Usern ihre Charaktere mit einem Klick zu sichern. Dieser Plugin basiert auf dem Post und Threads zu PDF Plugin von sparks fly.",
        "website"		=> "",
        "author"		=> "Ales",
        "authorsite"	=> "",
        "version"		=> "1.0",
        "guid" 			=> "",
        "codename"		=> "",
        "compatibility" => "*"
    );
}

function charactersecurity_install()
{
    global $db, $mybb;

    // Einstellungsgruppe
    $setting_group = array(
        'name' => 'charactersecurity',
        'title' => 'Charaktersicherung',
        'description' => 'Hier befinden sich die Einstellungen für die Charaktersicherung.',
        'disporder' => 3, // The order your setting group will display
        'isdefault' => 0
    );

    $gid = $db->insert_query("settinggroups", $setting_group);

    // Einstellungen
    $setting_array = array(
        // A text setting
        'charactersecurity_profilefields' => array(
            'title' => 'Profilfelder',
            'description' => 'Trage hier ein, welche Profilfelder abgefragt werden sollen.',
            'optionscode' => 'text',
            'value' => '1, 2, 3', // Default
            'disporder' => 1
        ),
    );

    foreach($setting_array as $name => $setting)
    {
        $setting['name'] = $name;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
    }

// Don't forget this!
    rebuild_settings();

    //Templates
    $insert_array = array(
        'title'		=> 'charasecure_profile',
        'template'	=> $db->escape_string('<a href="misc.php?action=charasecure&uid={$memprofile[\'uid\']}">{$lang->sc_profile}</a>'),
        'sid'		=> '-1',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

}

function charactersecurity_is_installed()
{

    global $mybb;
    if(isset($mybb->settings['charactersecurity_profilefields']))
    {
        return true;
    }

    return false;

}

function charactersecurity_uninstall()
{
    global $db;

    $db->delete_query('settings', "name IN ('charactersecurity_profilefields')");
    $db->delete_query('settinggroups', "name = 'charactersecurity'");
    $db->delete_query("templates", "title LIKE '%charasecure%'");
// Don't forget this
    rebuild_settings();

}

function charactersecurity_activate()
{
    global $db;

    include MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("member_profile", "#".preg_quote('{$online_status}')."#i", '{$charasecure}{$online_status}');
}

function charactersecurity_deactivate()
{
    global $db;

    include MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("member_profile", "#".preg_quote('{$charasecure}')."#i", '', 0);

}


// Profillink
$plugins->add_hook("member_profile_end", "charactersecurity_profile");

function charactersecurity_profile(){
    global $db, $mybb, $templates, $memprofile, $lang, $charasecure;

    $lang->load('charactersecurity');

    // aktive UID
    $active_uid = $mybb->user['uid'];


if($memprofile['uid'] == $active_uid AND $active_uid != 0 OR $mybb->usergroup['canmodcp'] == 1){
        eval("\$charasecure = \"".$templates->get("charasecure_profile")."\";");
    } else {
        $charasecure = "";
    }

}

// pdf generieren
$plugins->add_hook("misc_start", "charactersecurity_misc");
function charactersecurity_misc() {
    global $mybb, $db;
    require(MYBB_ROOT.'inc/3rdparty/cspdf.php');

    $profilefields = $mybb->settings['charactersecurity_profilefields'];

    $mybb->input['action'] = $mybb->get_input('action');
    if($mybb->input['action'] == "charasecure") {

        class finalPDF extends cSPDF
        {

            // Page footer
            function Footer()
            {
                $this->SetY(-15);
                $this->SetFont('Arial','',8);
                $this->Cell(0,10,'Seite '.$this->PageNo().'/{nb}',0,0,'R');
            }
        }

        $uid = (int)$mybb->input['uid'];
        $chara = get_user($uid);

        // Geburtstag richtig formatieren
        
            $membday = explode("-", $chara['birthday']);
            $bdayformat = fix_mktime($mybb->settings['dateformat'], $membday[2]);
            $membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
            $membday = date($bdayformat, $membday);

        // generate pdf
        $pdf = new finalPDF();
        $pdf->AliasNbPages();

        // content pages
        $pdf->title = $chara['username'];
        $pdf->AddPage();
        $pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
        $pdf->SetFont('Arial','B',14);
        $pdf->SetY(30);
        $pdf->MultiCell(185,10,$chara['username'],0,'C');
        $pdf->SetFont('Arial','',10);
        $pdf->MultiCell(185,10,"Charaktergeburtstag: ".$membday,0,'C');

        $pdf->title = $chara['username'];
        $profilefields = explode(", ", $profilefields);

        foreach ($profilefields as $profilefield) {
            $pfquery = $db->query("SELECT name 
            FROM " . TABLE_PREFIX . "profilefields
            WHERE fid = '{$profilefield}' 
            ORDER BY fid ASC");
            while ($post = $db->fetch_array($pfquery)) {

                $pdf->profilefields = $post['name'];

                // author
                $pdf->SetFont('DejaVu','',12);
                $pdf->SetX(30);
                $pdf->Cell(40, 10, $post['name']);

                $pdf->ln();
                $fid = "fid".$profilefield;
                $charaquery = $db->query("SELECT *
                    FROM ".TABLE_PREFIX."userfields
                    where ufid = '".$uid."'
                    ");

                $charauf = $db->fetch_array($charaquery);

                $charainfo = $charauf[$fid];
                // post
                $pdf->SetFont('DejaVu','',9);
                // Strip BBCode from Message
                $pattern = '|[[\/\!]*?[^\[\]]*?]|si';
                $replace = '';
                $charainfo = preg_replace($pattern, $replace, $charainfo);
                $pdf->SetX(30);
                $pdf->MultiCell(150, 5, strip_tags($charainfo));

                $pdf->ln();

            }


        }
        $title =  $chara['username'];

        $pdf->Output('I', $title.'.pdf');
    }
}

