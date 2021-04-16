<?php
/*
Plugin Name: ニーズカフェチェッカー
Plugin URI:
Description: ニーズカフェで使われる校正用プラグイン
Author: よきな
Version: 1.0.0
Author URI:
*/

function raitaa_do_checker ($content) {
    if(!isset($_GET['p']) || !isset($_GET['writer']) ){
        return;
    }
    $content = preg_replace( '/<p>|<\/p>/msi','',$content);

    $t = preg_split("/[\n|\r]+/", $content);

    $chapter = array(
        "number" => -1,
        "section" => 0,
        "line" => array('-1' => 0),
        "keyword" => null
    );
    $norma = array("kwcount"=> array(),"strong"=> 0, "color"=> 0 , "img"=> 0, "abst_list" => 0);
    $abstract = false;
    $results = array();
    $id = get_the_ID();
    $type = "";
    $raitaa_keyword = get_post_meta($id, 'raitaa_keyword', true);

    if($raitaa_keyword){
        $chapter["keyword"] = array_map(function($w) {
            $w2 = explode(",", $w);
            return array("kws"=>explode(",", $w), "patt" => "/(".implode(")|(", $w2).")/u"); },
        explode("-", $raitaa_keyword));
    }

    //メタディスクリプション
    $the_page_meta_description = (get_post_meta($id, 'the_page_meta_description', true));
    $tmp = mb_strlen($the_page_meta_description);

    $type = ($tmp > 120 || $tmp < 115) ? "warning":"debug";
    $results[-1]["meta_desc"] = array('type' => $type, 'data' => "{$the_page_meta_description}({$tmp}文字)");
    //メタキーワード
    $metakw = get_post_meta($id, 'the_page_meta_keywords', true);
    if($metakw){
        $metakw = explode(",", $metakw);
        $type = (count($metakw) < 4) ? "warning":"debug";
        $results[-1]["metakw"] = array('type' => $type, 'data' => (implode("-", $metakw))."(".(count($metakw)).")" );
    }else{
        $results[-1]["metakw"] = array('type' => "warning", 'data' => null );        
    }
    //メタタグ
    $tags = get_the_tags();
    if($tags){
        $tags = array_map(function($tag) { return $tag->name; },$tags);
    }else{
        $tags = array();
    }
    if($chapter["keyword"]){
        $type = (count($tags) !== count($chapter["keyword"][0]["kws"])) ? "warning":"debug";
    }

    $results[-1]["tag"] = array('type' => $type, 'data' => implode("-", $tags) . "(". (count($tags)).")");
    //カテゴリー
    $category = array_map(function($tag) { return $tag->name; },get_the_category());
    $type = (count($category) !== 1) ? "warning":"debug";
    $results[-1]["category"] = array('type' => $type, 'data' =>(implode("-", $category)));
    //パーマリンク
    $pm = ( get_post_field( 'post_name', get_post() ));
    $type = (!$pm) ? "warning":"debug";
    $results[-1]["post_name"] = array('type' => $type, 'data' => $pm);
    $eyecatch = get_singular_eyecatch_image_url();
    $type = (!preg_match("/{$pm}/", basename($eyecatch), $m)|| !$pm)  ? "warning":"debug";
    $results[-1]["eyecatch"] = array('type' => $type, 'data' => basename($eyecatch));

    $n = 0;
    $intro_count = 0;
    $tcount = count($t);
    $title_line = -1;
    $len_check = true;
    $ending_check = true;

    for ($i=0; $i < count($t); $i++) {

        //閉じタグチェック
        //内部リンク
        if(preg_match("/^<a href=/u", $t[$i], $matches)){
            $results[$i]["href"] = array("type"=> "warning", "data" =>$t[$i]);
            continue;
        }

        if(preg_match("/ようです|そうです/u", $t[$i], $matches)){
            $results[$i]["yodesu"] = array("type"=> "info", "data" =>$matches[0]);
        }

        $line = strip_tags($t[$i]);

        //吹き出し内は改行のルールはないですが、行が長くなるのは避けます(lightbulb)

        //空行の場合、下にhrか空行があるかチェック
        if(preg_match("/&nbsp;/u", $t[$i], $matches)){
            //これだと3空行があってもスルーされるな
            if(!preg_match("/<h2>|<h3>|speech-balloon|&nbsp;/", $t[$i+1], $matches)){
                $results[$i]["bad_blank"] = array("type"=> "warning", "data" =>$t[$i+1]);
            }
            continue;
        }
        //ボックスタグの場合リストタグと併用
        if(preg_match("/-box/u", $t[$i], $matches)){
            //タグ内全部見るべきだろうけど下だけ
            if($t[$i+2] !=="<ol>"){
                // error_log(print_r($t[$i+1]));
                // $results[$i]["no_list"] = array("type"=> "warning", "data" =>$t[$i]);
            }
        }
        //数字は全て半角
        if(preg_match("/[０-９]/u", $t[$i], $matches)){
            $results[$i]["zenkaku_num"] = array("type"=> "warning", "data" =>$matches[0]);
        }
        //あなたに向けて書く
        if(preg_match("/人も|人は|方も|方は/u", $t[$i], $matches)){
            $results[$i]["hito"] = array("type"=> "info", "data" => $matches[0]);
        }
        if(preg_match("/更に|殆ど|下さい|事は|そう言う|お早う|そんな風に|の方|出来る|恐る恐る|何時か|何処か|何故か|良い|捗る|後で|人達|電話を掛ける|ひと通り|ご免なさい|丁度|経つ|易い|何でも|頂いた|合わせて|行こう|致し|様々|全て|通り|そんな風/u", $t[$i], $matches)){
// var_dump(htmlspecialchars( $t[$i]));
            $results[$i]["kinku"] = array("type"=> "info", "data" => $matches[0]);
        }
        // 見出し<h2><h3>か最後まできたらキーワードチェック
        if(preg_match("/(<h2>).*<\/h2>|(<h3>).*<\/h3>/", $t[$i], $matches)
|| $tcount == ($i+1)
    ){
            //見出しに、はつけない
            if(preg_match("/、|。|「|」/u", $line, $_m)){
                $results[$i]["kanma"] = array("type"=> "warning", "data" =>$_m[0]);
            }
            //見出しの?、!が半角か
            if(preg_match("/？|！/u", $line, $_m)){
                $results[$i]["zenkaku_kigo"] = array("type"=> "warning", "data" =>$_m[0]);
            }

            if($norma["strong"] === 0 && !$abstract ){
                // error_log(print_r("{$t[$title_line]}\n"));
                $results[$title_line]["no_strong"] = array("type" =>"warning", "data" => $norma["strong"]);
            }

            if(@$matches[1] === "<h2>" || $tcount == ($i+1)){
                $ret = is_blank($t, $i, -2);

                $results[$i]["blank"] = array("type"=> $ret["type"], "data" => $ret["data"]);

                if($chapter["section"] === 1){
                    $results[$title_line]["section"] = array("type"=> "warning", "data" => $chapter["section"]);
                }
                //導入文の文字数が300~350
                // error_log(print_r("intro_count:{$intro_count}"));
                if($chapter["number"] === -1 ){
                    $intro_count -= 1;
                    $type = ($intro_count < 250 || $intro_count > 350) ? "warning":"debug";
                    $results[-1]["intro_count"] = array("type" => $type, "data"=>$intro_count);

                }

                if($norma["color"] === 0 && !$abstract ){
                    // error_log(print_r("{$t[$title_line]}\n"));
                    $results[$title_line]["no_color"] = array("type" =>"warning", "data" => $norma["color"]);
                }


                if($norma["img"] === 0 && $chapter["number"] !== -1){
                    $results[$title_line]["no_img"] = array("type" =>"warning", "data" => $norma["img"]);
                }

                // error_log(print_r("chap:{$chapter['number']} n:{$n}"));
                //章終わり。kwチェック
                $tmp = '';
                $type = "debug";
                //1こもない
                if(!$norma["kwcount"] ){
                    $type = "warning";
                }

                foreach ($norma["kwcount"] as $k => $v) {
                // error_log(print_r("\n$t[$i] {$k}が{$v}:\n"));
                    if($v < 3){
                        $type = "warning";

                    }
                    $tmp .= "{$k}:{$v} ";
                }

                $type = ($type === "warning" || count($norma["kwcount"]) <  count($chapter["keyword"][$n]["kws"])  ) ? "warning":"debug";

                $chap_no = get_summary($chapter["number"], $abstract);
                if($results[$title_line]["kwcheck"]["type"] !== "warning"){
                    $results[$title_line]["kwcheck"]["type"] = $type;
                }
                $kekka = ($type === "warning") ? "△" : "🌸";


                if($title_line === -1){
                    $results[$title_line]["kwcheck"]["data"] .= "{$kekka}{$chap_no} {$tmp}";

                }else{
                    $results[$title_line]["kwcheck"]["data"] .= "{$tmp}";
                    $results[-1]["kwcheck"]["type"] = $type;
                    $results[-1]["kwcheck"]["data"] .= "<br />{$kekka}{$chap_no} {$tmp}";
                }

                //見出し3の数
                $chapter["section"] = 0;
                $chapter["number"]++;
                //見出し2の添字チェック
                $chapter["line"][$chapter["number"]] = $i;
                // 前章が終わった。前節の数チェック(見出し3は2つ以上入れる)
                $title_line = $chapter["line"][$chapter["number"]];

                $n = $chapter["number"];
                if($chapter["number"] < 0){
                    $n = 0;
                }


                if($tcount == ($i+1)){
                    continue;
                }
                $norma = array("kwcount"=> array(),"strong"=> 0, "color"=> 0 , "img"=> 0 , "abst_list" => 0);

                //localhostの画像を使っていない

                if($line == "まとめ"){
                    $abstract = true;
                    $n = 0;
                    continue;
                }
                // $results[$i] = $line;    //array("type"=> "warning", "data" =>ここは共通
                $len = get_len($line);
                //見出し2の文字数が17~23
                if($len < 17){
                    $results[$i]["len_min"] = array("type"=> "warning", "data" =>"{$len}");
                }elseif($len > 22){
                    $results[$i]["len_max"] = array("type"=> "warning", "data" =>"{$len}");
                }else{
                    $results[$i]["h2_len"] = array("type"=> "debug", "data" =>"{$len}");
                }

                //指定キーワードが順番どおりに入る
                if(isset($chapter["keyword"][$n]) && preg_match_all($chapter["keyword"][$n]["patt"], $line, $matches)){
                    $tmp = $chapter["keyword"][$n]["kws"];
                    if(
                        $matches[0][0] !== $tmp[0] &&
                        $matches[0][1] !== $tmp[1] &&
                        $matches[0][2] !== $tmp[2]

                    ){
                        $results[$i]["keyword"] = array("type"=> "warning", "data" =>"指定キーワードが順番通りに入っていません");
                    }
                }
                //見出し2のキーワードの間に記号!,?,♪が入ってない
                if(isset($chapter["keyword"][$n]) && @preg_match("/{$tmp[0]}(.*?){$tmp[1]}(.*?){$tmp[2]}/u", $line, $m)){
                    if( count($chapter["keyword"][$n]["kws"]) === 2  ){
                        $tmp = $m[1];
                    }else{
                        $tmp = $m[1].$m[2];
                    }
                    if( get_len($tmp) > 6  ){
                        $results[$i]["between_long"] = array("type"=> "warning", "data" =>$tmp);

                    }
                    if(preg_match("/!|\?|♪|。|、/u", $tmp, $matches)){
                        $results[$i]["between"] = array("type"=> "warning", "data" =>implode($matches, ""));
                    }
                }

            }elseif($matches[2] === "<h3>"){
                $ret = is_blank($t, $i, -1);
                $results[$i]["blank"] = array("type"=> $ret["type"], "data" => $ret["data"]);

                //見出し3は短くできるなら短い方がいい
                $chapter["section"]++;
            }

        }else{
            //localhostの画像
            if(preg_match("/http:\/\/localhost(.*?)\"/x", $t[$i], $matches)){
                $results[$title_line]["localhost"] = array("type"=> "warning", "data" => (get_summary($chapter["number"], $abstract)) ." ". ($matches[0]) );
            }


            if(preg_match("/<\/div>/", $t[$i], $matches)){
                $len_check = true;
                $ending_check = true;
            }

            if($len_check && preg_match("/<div class=\"(.*?)\">/", $t[$i], $matches)){
                $len_check = false;
                if($matches[1] !== "speech-balloon"){
                    $ending_check = false;
                }
            }
            //見出し2(まとめも)の下に画像がある
            if(preg_match("/src=.+?\".*? \"?/x", $t[$i], $matches)){
                $file = pathinfo($matches[0]);
                //ノルマの画像か
                if(preg_match("/{$pm}/", $file['basename'], $m)){
                    $norma["img"] += 1;
                    //画像のサイズが横300形式がjpg
// var_dump($pm);
                    if($file['extension'] !== 'jpg"' ){
                        $results[$title_line]["img_ext"] = array("type"=> "warning", "data" =>$file['extension']);
                    }

                    //横サイズが300
                    if(preg_match("/width\=\"([0-9]+) /x", $t[$i], $matches)){
                        if($matches[1] !== "300"){
                            $results[$title_line]["img_width"] = array("type"=> "warning", "data" =>$matches[1]);
                        }
                    }

                }

            }

            //空行でもない空白の場合(divタグなど)
            if($line == ""){
                if($chapter["number"] === -1){
                    // $intro_count += 0.5;
                }
                continue;
            }
            //導入文の文字数をプラス
            if($chapter["number"] === -1){
                $intro_count += 1;
                $intro_count += get_len($line);
            }


            //リストタグの場合は字数や文末をチェックしない
            if($len_check && !preg_match("/<li>|<\/li>/u", $t[$i], $matches)){
                $ending_check = false;
                //改行までの文字列がスマホで2行~4行に収まる
                $l = get_len($line);
                // error_log(print_r("get_len:{$line}\n"));
                if($l < 22){
                    $results[$i]["tooshort"] = array("type"=> "warning", "data" =>$l);
                    //下の行がリストタグ
                }elseif($l > 84){
                    $results[$i]["toolong"] = array("type"=> "warning", "data" =>$l);
                }
            }
            if($ending_check){
                //文末に。か？か！か♪が入っている(まとめ、空行、タイトル、テーブル以外)
                if(!preg_match("/(？|！|。|♪|\)|」|）)$/u", $line, $matches)){
                    //下の行がリストタグ
                    preg_match("/.$/u", $line, $matches);
                    $results[$i]["ending"] = array("type"=> "warning", "data" =>$matches[0]);
                }
            }

            //まとめの箇条書きカウント
            if(preg_match("/<li>|<\/li>/u", $t[$i], $matches)){
                if($abstract){
                    $norma["abst_list"] += 1;
                }else{
                    //<div class="blank-box
                    //まとめ以外の箇条書きの場合上に文章があること
                    //箇条書きの場合は間に。を入れない
                    if(preg_match("/。/u", $t[$i], $matches)){
                        $results[$i]["kuten"] = array("type"=> "warning", "data" =>$matches[0]);
                    }
                    if(preg_match("/^<h2>|^<h3>/u", $t[$i-1], $matches)){

                    }
                }
            }




            //shift+enter
            if(preg_match("/^<div>.*<\/div>$/u", $t[$i], $matches)){
                $results[$i]["enter"] = array("type"=> "warning", "data" =>$matches);
            }
            //文頭にですが
            if(preg_match("/^ですが/u", $line, $matches)){
                $results[$i]["desuga"] = array("type"=> "warning", "data" =>$matches[0]);
            }

            //文頭になので
            if(preg_match("/^なので/u", $line, $matches)){
                $results[$i]["nanode"] = array("type"=> "warning", "data" =>$matches[0]);
            }

            //文中で!?は使わない
            if(preg_match("/[!|?]/u", $line, $matches)){
                $results[$i]["hankaku_kigo"] = array("type"=> "warning", "data" =>$matches[0]);
            }
            if(preg_match("/<strong>/u", $t[$i], $matches)){
                //まとめの場合は警告
                if($abstract){
                    $results[$i]["abst_tag"] = array("type"=> "warning", "data" =>$matches[1]);
                //導入文ですでにある場合は警告
                }elseif(($chapter["number"] === -1) && $norma["strong"] > 0){
                    $results[$i]["too_strong"] = array("type"=> "warning", "data" =>$matches[1]);
                }else{
                    if(preg_match("/<strong>.*<\/strong>/u", $t[$i], $matches)){
                        //Bタグとcolorタグは一行に修飾。
                        //修飾ノルマクリア
                        $norma["strong"] += 1;
                    }
                }
            }
            if(preg_match("/<span style=\"color:/u", $t[$i], $matches)){

                //まとめの場合は警告
                if($abstract){
                    $results[$i]["abst_tag"] = array("type"=> "warning", "data" =>$matches[0]);
                }else{
                // if(preg_match("/src=.+?\"(.*?)\\\ /x", $t[$i+1], $matches)){
                    if(preg_match("/<span style.*(！|。|？|」)<\/span>/u", $t[$i], $matches)){
                        //Bタグとcolorタグは一行に修飾。
                        //修飾ノルマクリア
                        $norma["color"] += 1;
                    }
                }

            }

            if(isset($chapter["keyword"][$n])
                // && $i !== $chapter["line"][$chapter["number"]]
                 ){
        // $chapter["keyword"] = array_map(function($w) { return explode("-", $w); },
        // $chapter["keyword"][$n]);

                if(preg_match_all($chapter["keyword"][$n]["patt"], $line, $matches)){
                    foreach ($matches[0] as $k => $v) {
                        $norma["kwcount"][$v] += 1;
                        if(!preg_match("/(<h2>).*<\/h2>|(<h3>).*<\/h3>/", $t[$i], $matches)){
                            $t[$i] = preg_replace("/{$v}?/","<span class='proofreading-item color".(array_search($v, $chapter["keyword"][$n]["kws"])+1)."'
                                title=この行までで全". $norma['kwcount'][$v]. "回
                                '>{$v}</span>", $t[$i]);

                        }
                    }
                }
            }


            //テーブルチェック

        }

        //文章の途中にリンクを入れない
        //テーブルタグを使う場合カラム名は行名はstrongを入れる、中央揃え

        //皆さんはダメ
        //autokanaで。中身を見れば英字になってるところあるはず画像名生成　zuでもduでもでるのか
        //2重装飾は控える
        //word校正はしましたか？、コピペチェックは%でしたか？添削依頼表をセットし、添削が終わったら黄色を塗ろう(依頼表へのリンクGoogleスプレッドシートAPIでできるかも)。リビジョンを更新しましょう、編集画面を閉じましょう

    }
    //まとめのリストタグが4000文字を超える場合6~8
    $type = (get_len(strip_tags($content)) > 4000
        && $norma["abst_list"] < 6 ) || ($norma["abst_list"] < 4) ? "warning":"debug";
    $results[-1]["abst_list"] = array('type' => $type, 'data' => $norma["abst_list"]);

    $results[-1]["article_length"] = array('type' => "debug", 'data' => get_len( strip_tags($content)) );
    //見出し2はまとめいれて4つ以上
    $type = ($chapter["number"] < 3) ? "warning":"debug";
    $results[-1]["chap_no"] = array('type' => $type, 'data' =>$chapter["number"]);

    // $a = get_post_meta_by_id($_GET['preview_id']);
    // $b = get_post_meta($a["meta_id"]);
    // error_log(print_r( get_post($_GET['preview_id'] )->post_title));
    $data = get_post($_GET['p'] );
    $tmp = preg_split("/(　| )+/", $data->post_title);
    $title = $tmp[count($tmp)-1];
    $len = get_len($title);
    //タイトルが28~32文字
    $type = ($len < 28 || $len > 32) ? "warning":"debug";
    $results[-1]["title_len"] = array('type' => $type, 'data' => $title."(".$len."文字)");


    if(preg_match("/、|。|「|」|①/u", $title, $_m)){
        $results[-1]["kanma"] = array("type"=> "warning", "data" =>$_m[0]);
    }
    //見出しの?、!が半角か
    if(preg_match("/？|！/u", $title, $_m)){
        $results[-1]["zenkaku_kigo"] = array("type"=> "warning", "data" =>$_m[0]);
    }
    //Bとタイトルと目次をあわせる

    //ステータスチェック
    $type = !preg_match("/作成中|添削依頼/u", $tmp[0], $_) ? "warning":"debug";
    $results[-1]["title_format"] = array('type' => $type, 'data' => $data->post_title);

    $warning  = "<div class='proofreading-result'>
<div class='proofreading-summary'>
<p><span class='proofreading-h2'>サマリー</span></p>";

    for ($i=-1; $i < count($t)+1; $i++) {
        $type = "debug";
        $desc = "";
        if(isset($results[$i])){
            if($i === -1){
                foreach ($results[$i] as $k => $v) {
                    if(!check_display($k)){
                        continue;
                    }
                    $warning .="<span class='proofreading lv_".$v["type"]."'>".level_head($v["type"]);
                    $warning .= warning_desc($k, $v["data"]) ."<br /></span>";
                }
                $warning  .= "<p><span class='proofreading-h2'>本文</span></p>";
            }else{
                foreach ($results[$i] as $k => $v) {
                    if(!check_display($k)){
                        continue;
                    }

                    if($v["type"] === "warning"){
                       $type = "warning";
                    }
                    if($v["type"] === "info"){
                       $type = "info";
                    }

                    $desc .= ("\r\n").(level_head($v["type"]))  .(strip_tags(warning_desc($k, $v["data"]))) ;
                }
                // echo "v:".($v["type"])." type:" .($type)."<br />";
                if($desc !== ""){
                   $warning .="<span class='proofreading-item lv_".$type."'  title='".level_desc($type)."{$desc}'>$t[$i]</span><br />";
                }else{
                $warning .= "<p>".$t[$i]."</p>";
                }
            }
        }else{
            $warning .= "<p>".$t[$i]."</p>";
        }
// var_dump(  (htmlspecialchars($warning)));
// var_dump(  "----<br />\n" );

    }
    $type = !($chapter["keyword"][0]["kws"]) ? "warning":"debug";
    if($chapter["keyword"]){
        $results[-1]["keyword"] = array('type' => "debug", 'data' => (implode($chapter["keyword"][0]["kws"], " ") ));
        $reduced_kws = array($chapter["keyword"][0]["kws"][0],$chapter["keyword"][0]["kws"][1]);
        $query =  urlencode(implode($reduced_kws, " ") ) ;
        $warning  .= "
https://related-keywords.com/result/suggest?q={$query}
https://rakko.tools/tools/3/
https://ccd.cloud/";

        global $current_user;
        if($_SERVER["HTTP_HOST"] === "localhost:8080" || get_currentuserinfo()->user_nicename === "mail5d98"){
            $warning  .= "
https://docs.google.com/spreadsheets/d/1Am84Wf2HDFCkfeXNKn3kF4gwfwtquAF3sNkFdTlawfk/edit#gid=112081895
            ";
        }

    }

    return $warning ;
}

function check_display($warning) {
    global $current_user;
    if($_SERVER["HTTP_HOST"] === "localhost:8080" || get_currentuserinfo()->user_nicename === "mail5d98"){
        return true;
    }
    switch ($warning) {
        case "h2_len":
        case "len_max":
        case "len_min":
        case "tag":
        case "category":
        case "title_format":
        case "abst_list":
        case "post_name":
        case "kuten":
        case "hito":
        case "kinku":
        case "yodesu":
        case "kanma":
        case "keyword":
        case "ending":
        case "no_strong":
        case "chap_no":
        case "no_color":
            return false;
            break;
        default:
            return true;
            break;
    }
}

function level_head($level) {
    switch ($level) {
        case 'debug':
            return "🌸";
            break;
        case 'info':
            return "🧐";
            break;
        case 'warning':
            return "△";
            break;        
        default:
            break;
    }
}

function level_desc($level) {
    switch ($level) {
        case 'debug':
            return "すべてOK🎉";
            break;
        case 'info':
            return "確認だけ";
            break;
        case 'warning':
            return "△あり";
            break;        
        default:
            break;
    }
}

function warning_desc($warning, $val) {
    if($warning !== "kwcheck" && !is_array($val)){
        $val = strip_tags($val);
    }
    switch ($warning) {
        case "keyword":
            $result = sprintf("指定キーワード【%s】", $val);
            break;
        case "blank":
            $result = sprintf("見出し前の改行(見出し2は2行,3なら1行)【%s行】", abs($val));
            break;
        case "h2_len":
            $result = sprintf("見出しの文字数(17~22) 【%s文字】", $val);
            break;
        case "len_max":
            $result = sprintf("タイトルが長過ぎます(20文字前後) 【%s文字】", $val);
            break;
        case "len_min":
            $result = sprintf("タイトルが短すぎます(20文字前後) 【%s文字】", $val);
            break;
        case "bad_blank":
            $result = sprintf("見出しや吹き出しの前以外で改行が入っています 【%s】", $val);
            break;
        case "hankaku_kigo":
            $result = sprintf("見出し以外で半角の!や?が使われています△ 【%s】", $val);
            break;
        case "ending":
            $result = sprintf("？ ！ 。 ♪ ) 以外の文末です 【%s】△", $val);
            break;
        case "tooshort":
            $result = sprintf("スマホで見ると1行です 22~84文字推奨【現在%s文字】△", $val);
            break;
        case "toolong":
            $result = sprintf("スマホで見ると4行以上です 21~84文字推奨【現在%s文字】△", $val);
            break;
        case "no_color":
            $result = sprintf("赤か青の装飾がある", $val);
            break;
        case "kwcount":
            $result = sprintf("キーワード埋め込み %s", $val);
        case "kwcheck":
            $result = sprintf("キーワード埋め込み(それぞれ3以上)</span><br />%s", $val);
            break;
        case "kw0":
            $result = sprintf("埋め込みキーワードの数(3)</span><br />%s", $val);
            break;
        case "meta_desc":
            $result = sprintf("メタディスクリプション(115~120文字) </span><br />%s", $val);
            break;
        case "metakw":
            $result = sprintf("メタキーワード(4~6) </span><br />%s", $val);
            break;
        case "tag":
            $result = sprintf("タグ(3つ) </span><br />%s", $val);
            break;
        case "category":
            $result = sprintf("カテゴリー(1つ) </span><br />%s", $val);
            break;
        case "zenkaku_num":
            $result = sprintf("数字が全て半角 </span><br />【%s】", $val);
            break;
        case "chap_no":
            $result = sprintf("見出し2の数(4以上) </span><br />%s", $val);
            break;
        case "title_len":
            $result = sprintf("タイトルの文字数(28~32) </span><br />%s", $val);
            break;
        case "title_format":
            $result = sprintf("タイトルの形式が「作成中or添削依頼or修正依頼　○記事目 タイトル」 </span><br />%s", $val);
            break;
        case "intro_count":
            $result = sprintf("導入文の文字数(300±) </span><br />%s文字", $val);
            break;
        case "abst_list":
            $result = sprintf("まとめの箇条書き(4000文字以上の場合は6~8) </span><br />%s", $val);
            break;
        case "post_name":
            $result = sprintf("パーマリンク(指定キーワードをそのままローマ字に) </span><br />%s", $val);
            break;
        case "eyecatch":
            $result = sprintf("アイキャッチ </span><br />%s", $val);
            break;
        case "kwmissing":
            $result = sprintf("埋め込まれたキーワードの種類 </span><br />%s種類", $val);
            break;
        case "article_length":
            $result = sprintf("記事の文字数 (こぴらん数え上げ)</span><br />%s文字数", $val);
            break;
        case "too_strong":
            $result = sprintf("導入文ではstrongタグは1つのみ</span><br />%s", $val);
            break;
        case "no_strong":
            $result = sprintf("strongタグがありません</span><br />%s", $val);
            break;
        case "kanma":
            $result = sprintf("見出しに記号が入っています</span><br />【%s】", $val);
            break;
        case "no_img":
            $result = sprintf("画像が入っていないか画像名が違います</span><br />【%s】", $val);
            break;
        case "hito":
            $result = sprintf("〜な人、ではなくあなたに向けて書く 【%s】", $val);
            break;
        case "kinku":
            $result = sprintf("開いたほうが良い漢字かも 【%s】", $val);
            break;
        case "yodesu":
            $result = sprintf("可能であれば言い切り 【%s】", $val);
            break;
        case "between":
            $result = sprintf("指定キーワードの間に記号が入っています</span><br />：%s", $val);
            break;
        case "zenkaku_kigo":
            $result = sprintf("見出しでは記号は半角で入力してください</span><br />：【%s】", $val);
            break;
        case "section":
            $result = sprintf("見出し3は2つ以上入れてください：【%sつ】", $val);
            break;
        case "localhost":
            $result = sprintf("ローカルの画像が使われています：<br />%s", $val);
            break;
        case "kuten":
            $result = sprintf("箇条書きに句点が使われています：<br />%s", $val);
            break;
        case "nanode":
            $result = sprintf("「なので」は使わない：<br />%s", $val);
            break;
        case "between_long":
            $result = sprintf("キーワードが左詰めになっていません<br />【%s】", $val);
            break;
        case "img_ext":
            $result = sprintf("画像の拡張子がjpg以外です【%s】", $val);
            break;
        case "img_width":
            $result = sprintf("画像の幅が300以外です【%s】", $val);
            break;
        default:
            $result = sprintf("{$warning} %s", $val);
            break;
    }
    return $result;
}


function get_len( $text ){
    return (mb_strwidth(($text))/2);

  $minus_lf = 0;
  $tempWholeLen = strlen(mb_convert_encoding($text, "SJIS", "ASCII,JIS,UTF-8,EUC-JP,SJIS")) / 2;
  //改行の数を検出
  preg_match_all("\n|\r\n|\r", $text, $matches, PREG_PATTERN_ORDER);
  $lfCount = count($matches[0]);
  $minus_lf += ($lfCount/2);//改行1つを0.5文字として扱う
  $modifiedWholeLen = $tempWholeLen - $minus_lf;//減算
  return $modifiedWholeLen;
}

function get_summary($chap_no, $abstract) {
    if($chap_no === -1){
        return "導入文";
    }elseif ($abstract) {
        return "まとめ";
    }else{
        return "見出し2-".($chap_no+1);
    }
}
function is_blank($t, $i, $check) {
    $tmp = -1;
    while (true) {
        if($t[$i+$tmp] !== "&nbsp;"){
            $tmp+=1;
            break;
        }
        $tmp-=1;
    }
    if($tmp === $check){
        return array("type" => "debug", "data" => $tmp);
    }else{
        return array("type" => "warning", "data" => $tmp);
    }
}

function raitaa_css () {
    //プレビュー画面かつ「校正情報プレビュー」ボタンから呼ばれた時にのみ処理を実施
    //is_preview()が正常に動作しないケースに遭遇したため、クエリストリングでプレビュー状態かどうか判断しています。
    if(isset($_GET['p']) and isset($_GET['writer']) ){
        wp_register_style(
            'proofreading',
            plugins_url('css/raitaa.css', __FILE__),
            array(),
            1.0,
            'all'
        );
        wp_enqueue_style('proofreading');
    }
}
//クエリストリングより文章構成支援のオンオフを判定し、必用な時のみアクションとフィルターをフックする。
//※条件を絞らないとフックされる機会が多すぎるのでは無いかと考えたため。
if( isset($_GET['writer']) ){
    add_action('wp_enqueue_scripts', 'raitaa_css');
    add_filter('the_content','raitaa_do_checker');
}






function writer_add_button() {
    global $post;
    global $current_user;
// var_dump(get_currentuserinfo()->user_nicename);
// var_dump($GLOBALS['current_screen']->in_admin( $user));
//$user = $current_user->user_level
    $query_args = array();
    if ( get_post_type_object( $post->post_type )->public ) {
        if(get_currentuserinfo()->user_nicename === "mail5d98" || $current_user->user_level >5){
            if ( 'publish' == $post->post_status || $user->ID != $post->post_author ) {
                // Latest content is in autosave
                $nonce = wp_create_nonce( 'post_preview_' . $post->ID );
                // $query_args['preview_id'] = $post->ID;
                $query_args['preview_nonce'] = $nonce;
                //判別用にクリエストリング「proofreading=yes」を追加
                $query_args['preview'] = 'true';
                $query_args['writer'] = 'yes';

                $url = html_entity_decode(esc_url(add_query_arg($query_args, get_permalink($page->ID))));
echo $url
?>
<script>

    (function($) {
        $('#minor-publishing-actions').append('<div class="proofreading-preview"><a id="proofreading-preview" class="button">添削アシスト</a></div>');
        $(document).on('click', '#proofreading-preview', function(e) {
            e.preventDefault();
            PreviewURL = '<?php echo $url ?>';
            window.open(PreviewURL);
        });
    }(jQuery));
</script>
<?php
            }
        }
    }
}

function writer_add_button_columns($columns) {
    global $post;
    global $current_user;
    if(get_currentuserinfo()->user_nicename !== "mail5d98" &&
       get_currentuserinfo()->user_nicename !== "wp"){
        return $columns;
    }
    $columns['raitaa_check'] = "添削";
    return $columns;
}


function add_column_value ($column_name, $post_ID) {
    if($column_name !== "raitaa_check"){
        return;
    }
    global $post;
    global $current_user;
    $query_args = array();
    if ( get_post_type_object( $post->post_type )->public ) {
        if(get_currentuserinfo()->user_nicename === "mail5d98" || $current_user->user_level >5){
            if ( 'publish' == $post->post_status || $user->ID != $post->post_author ) {
                // Latest content is in autosave
                $nonce = wp_create_nonce( 'post_preview_' . $post->ID );
                // $query_args['preview_id'] = $post->ID;
                $query_args['preview_nonce'] = $nonce;
                //判別用にクリエストリング「proofreading=yes」を追加
                $query_args['preview'] = 'true';
                $query_args['writer'] = 'yes';

                $url = html_entity_decode(esc_url(add_query_arg($query_args, get_permalink($page->ID))));
echo "<a href=".$url.">添削</a>";

            }
        }
    }

}
add_action( 'manage_posts_custom_column', 'add_column_value', 10, 2 );


register_setting( 'weiting_setting', 'weiting_setting', 'sanitize' );

add_action( 'admin_footer-post-new.php', 'writer_add_button' );
add_action( 'admin_footer-post.php', 'writer_add_button' );
add_action('manage_posts_columns', 'writer_add_button_columns' );

add_action('admin_print_styles', function () {
  echo '<style>
  .column-raitaa_check{
    width:30px;
  }
  </style>'.PHP_EOL;
});


function raitaa_keyword_meta_box() {

    add_meta_box(
        'raitaa-keyword',
        __( '指定キーワード', 'sitepoint' ),
        'raitaa_keyword_meta_box_callback',
        'post'
    );
}

add_action( 'add_meta_boxes', 'raitaa_keyword_meta_box' );

function raitaa_keyword() {

    $args = array(
        'label'                => '指定キーワード',
        'public'               => true,
        'register_meta_box_cb' => 'raitaa_keyword_meta_box'
    );

    register_post_type( 'raitaa_keyword', $args );
}

// add_action( 'init', 'raitaa_keyword' );

function raitaa_keyword_meta_box_callback( $post ) {

    // Add a nonce field so we can check for it later.
    wp_nonce_field( 'raitaa_keyword_nonce', 'raitaa_keyword_nonce' );

    $value = get_post_meta( $post->ID, 'raitaa_keyword', true );
    echo '<input type="text" id="raitaa_keyword" name="raitaa_keyword" value="'.esc_attr($value).'" size="50" />';
    echo '書き方：<br /><p class="howto">見出し2-1のキーワード1,見出し2-1のキーワード2,見出し2-1のキーワード3-見出し2-2のキーワード1,見出し2-2のキーワード2,見出し2-2のキーワード3と書いてください<br />
    例:パフ,洗う,頻度-パフ,洗う,ダイソー-パフ,洗う,石鹸</p>';

}
function save_raitaa_keyword_meta_box_data( $post_id ) {

    // Check if our nonce is set.
    if ( ! isset( $_POST['raitaa_keyword_nonce'] ) ) {
        return;
    }

    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $_POST['raitaa_keyword_nonce'], 'raitaa_keyword_nonce' ) ) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check the user's permissions.
    if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }

    }
    else {

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }

    /* OK, it's safe for us to save the data now. */
    // Make sure that it is set.
    if ( ! isset( $_POST['raitaa_keyword'] ) ) {
        return;
    }
    // Sanitize user input.
    $my_data = sanitize_text_field( $_POST['raitaa_keyword'] );
    // Update the meta field in the database.
    update_post_meta( $post_id, 'raitaa_keyword', $my_data );
}

add_action( 'save_post', 'save_raitaa_keyword_meta_box_data' );
