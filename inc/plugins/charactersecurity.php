<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}


function charactersecurity_info()
{
    return array(
        "name"			=> "Charaktersicherung",
        "description"	=> "Ermöglicht Usern ihre Charaktere mit einem Klick zu sichern.",
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
    find_replace_templatesets("member_profile", "#".preg_quote('{$online_status}')."#i", '{$charasecure}{$printthread}');
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
    if($mybb->user['uid'] != 0) {
        $active_uid = $mybb->user['uid'];
        if ($memprofile['uid'] == $active_uid and $active_uid != 0 or $mybb->usergroup['canmodcp'] == 1) {
            eval("\$charasecure = \"" . $templates->get("charasecure_profile") . "\";");
        } else {
            $charasecure = "";
        }
    } else {
        $active_uid = 0;
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
        // GÄSTE KÖNNEN DEN INHALT NICHT SEHEN
        if($mybb->user['uid'] == 0)
        {
            error_no_permission();
        }
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
        $membday = "";
        // Geburtstag richtig formatieren
        if($chara['birthday']) {
            $membday = explode("-", $chara['birthday']);
            $bdayformat = fix_mktime($mybb->settings['dateformat'], $membday[2]);
            $membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
            $membday = date($bdayformat, $membday);
        }

        // generate pdf
        $pdf = new finalPDF();
        $pdf->AliasNbPages();

        // content pages
        $pdf->username = $chara['username'];
        $pdf->AddPage();
        $pdf->AddFont('Arial','','ARIAL.TTF',true);
        $pdf->AddFont('Arialb','B','ARIALBD.TTF',true);
        $pdf->AddFont('Calibri','','CALIBRI.TTF',true);
        $pdf->AddFont('Calibrib','B','CALIBRIB.TTF',true);
        $pdf->SetFont('Arial','B',20);
        $pdf->SetY(20);

        // Oben Usernamen ausgeben
        $pdf->MultiCell(185,5,$chara['username'],0,'C');

        // Geburtstag auslesen
        $pdf->SetFont('Calibrib','B',12);
        $pdf->SetX(20);
        $pdf->MultiCell(185, 5, "Geburtstag");
        $pdf->SetFont('Calibri','',9);
        $pdf->SetX(20);
        $pdf->MultiCell(170, 5, $membday);
        $pdf->ln();

        // Beruf auslesen
        $job_query = $db->query("SELECT name
        FROM ".TABLE_PREFIX."jobs
        where jid = '".$chara['jid']."'
        ");

        $jobselect = $db->fetch_array($job_query);
        $jobplace = $jobselect['name'];

        $pdf->SetFont('Calibrib','B',12);
        $pdf->SetX(20);
        $pdf->MultiCell(185, 5, "Job");
        $pdf->SetFont('Calibri','',9);
        $pdf->SetX(20);
        $pdf->MultiCell(170, 5, $chara['job']." (".$jobplace.")");
        $pdf->ln();

        $profilefields = explode(", ", $profilefields);

        foreach ($profilefields as $profilefield) {
            $pfquery = $db->query("SELECT name 
            FROM " . TABLE_PREFIX . "profilefields
            WHERE fid = '{$profilefield}' 
            ORDER BY fid ASC");
            while ($post = $db->fetch_array($pfquery)) {

                $pdf->profilefields = $post['name'];

                // profilfeldname auslesen
                $pdf->SetFont('Calibrib','B',12);
                $pdf->SetX(20);
                $pdf->Cell(20, 5, $post['name']);

                $pdf->ln();
                $fid = "fid".$profilefield;
                $charaquery = $db->query("SELECT *
                    FROM ".TABLE_PREFIX."userfields
                    where ufid = '".$uid."'
                    ");

                $charauf = $db->fetch_array($charaquery);

                $charainfo = $charauf[$fid];
                // userfeld auslesen
                $pdf->SetFont('Calibri','',9);
                // BBcodes entfernen
                $pattern = '|[[\/\!]*?[^\[\]]*?]|si';
                $replace = '';
                $charainfo = preg_replace($pattern, $replace, $charainfo);
                $pdf->SetX(20);
                $pdf->MultiCell(170, 5, strip_tags($charainfo));

                $pdf->ln();

            }


        }


        $title =  $chara['username'];

        $pdf->Output('I', $title.'.pdf');
    }
}

