<?php
/*
Plugin Name: raitaa
Plugin URI: 
Description: WEBライティングの記事をチェックします
Author: よきな
Version: 1.0.0
Author URI:
*/


function raitaa_do_checker ($content) {
    if(!isset($_GET['preview_id']) || !isset($_GET['writer']) ){
        return;
    }

    $content = preg_replace( '/<p>|<\/p>/msi','',$content);

    $t = preg_split("/[\n|\r,]+/", $content);

    $chapter = array(
        "number" => -1,
        "section" => 0,
        "line" => array('-1' => 0),
        "keyword" => null
    );
    $norma = array("kwcount"=> array(),"strong"=> 0, "color"=> 0 , "abst_list" => 0);
    $abstract = false;
    $results = array();
    $id = get_the_ID();
    $type = "";
    $raitaa_keyword = get_post_meta($id, 'raitaa_keyword', true);
    if($raitaa_keyword){
        $chapter["keyword"] = array_map(function($w) { return explode("-", $w); },
        explode(",", $raitaa_keyword));
    }
    //メタディスクリプション
    $the_page_meta_description = (get_post_meta($id, 'the_page_meta_description', true));
    $tmp = get_len($the_page_meta_description);
    $type = ($tmp > 120 || $tmp < 100) ? "warning":"debug";
    $results[-1]["meta_desc"] = array('type' => $type, 'data' => "{$the_page_meta_description}({$tmp}文字)");
    //メタキーワード
    $metakw = get_post_meta($id, 'the_page_meta_keywords', true);
    if($metakw){
        $metakw = explode(",", $metakw);
        $type = (count($metakw) < 5) ? "warning":"debug";
        $results[-1]["metakw"] = array('type' => $type, 'data' => (implode("-", $metakw))."(".(count($metakw)).")" );

    }
    //メタタグ
    $tags = get_the_tags();
    if($tags){
        $tags = array_map(function($tag) { return $tag->name; },$tags);
    }else{
        $tags = array();
    }
    $type = (count($tags) !== 3) ? "warning":"debug";
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
    $e = explode("/", $eyecatch);
    $e = $e[count($e)-1];
    $type = (!preg_match("/{$pm}/", $e, $m)|| !$pm)  ? "warning":"debug";
    $results[-1]["eyecatch"] = array('type' => $type, 'data' => $e);

    $n = 0;
    $intro_count = 0;
    $tcount = count($t);
    $title_line = -1;
    $len_check = true;
    for ($i=0; $i < count($t); $i++) {

        //閉じタグチェック


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
                $results[$i]["no_list"] = array("type"=> "warning", "data" =>$t[$i]);
            }
        }
        //数字は全て半角
        if(preg_match("/[０-９]/u", $t[$i], $matches)){
            $results[$i]["zenkaku_num"] = array("type"=> "warning", "data" =>$t[$i]);
        }
        // 見出し<h2><h3>か最後まできたらキーワードチェック
        if(preg_match("/(<h2>).*<\/h2>|(<h3>).*<\/h3>/", $t[$i], $matches)
|| $tcount == ($i+1)
    ){
            //見出しに、はつけない
            if(preg_match("/、|「|」|①/u", $line, $_m)){
                $results[$i]["kanma"] = array("type"=> "warning", "data" =>$_m[0]);
            }
            //見出しの?、!が半角か
            if(preg_match("/？|！|♪/u", $line, $_m)){
                $results[$i]["zenkaku_kigo"] = array("type"=> "warning", "data" =>$_m[0]);
            }

            if($t[$i-1] !== "&nbsp;"){
                // $results[$i]["no_blank"] = array("type"=> "warning", "data" =>substr($line, 0,5));
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
                    $intro_count -=0.5;
                    $type = ($intro_count < 300 || $intro_count > 350) ? "warning":"debug";
                    $results[-1]["intro_count"] = array("type" => $type, "data"=>$intro_count);

                }

                if($norma["color"] === 0 && !$abstract ){
                    // error_log(print_r("{$t[$title_line]}\n"));
                    $results[$title_line]["no_color"] = array("type" =>"warning", "data" => $norma["color"]);
                }

                if($norma["strong"] === 0 && !$abstract ){
                    // error_log(print_r("{$t[$title_line]}\n"));
                    $results[$title_line]["no_strong"] = array("type" =>"warning", "data" => $norma["strong"]);
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
                $type = ($type === "warning" || count($norma["kwcount"]) !== 3) ? "warning":"debug";

                $chap_no = get_summary($chapter["number"], $abstract);
                if($results[$title_line]["kwcheck"]["type"] !== "warning"){
                    $results[$title_line]["kwcheck"]["type"] = $type;
                }
                $kekka = ($type === "warning") ? "△" : "🌸";

                if($title_line === -1){
                    $results[$title_line]["kwcheck"]["data"] .= "{$kekka}{$chap_no}:{$tmp}";

                }else{
                    $results[$title_line]["kwcheck"]["data"] .= "{$tmp}";
                    $results[-1]["kwcheck"]["type"] = $type;
                    $results[-1]["kwcheck"]["data"] .= "<br />{$kekka}{$chap_no}:{$tmp}";
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
                $norma = array("kwcount"=> array(),"strong"=> 0, "color"=> 0 , "abst_list" => 0);
                //直前に空行2行ある
                if($t[$i-2] !== "&nbsp;"){
                // error_log(print_r("\n{$t[$i-1]}\n{$t[$i-2]}\n"));
                    // $results[$i]["no_blank"] = array("type"=> "warning", "data" =>$t[$i-2]);
                }

                //localhostの画像を使っていない

                //見出し2(まとめも)の下に画像がある
                if(preg_match("/src=.+?\"(.*?) \"?/x", $t[$i+1], $matches)){
                    //画像のサイズが横300形式がjpg
                    if(substr($matches[0], -4,3) !== "jpg" ){
                        $results[$i]["img_ext"] = array("type"=> "warning", "data" =>substr($matches[1], 3));
                    }

                    //横サイズが300
                    if(preg_match("/width\=\"([0-9]+) /x", $t[$i+1], $matches)){
                        if($matches[1] !== "300"){
                            $results[$i]["img_width"] = array("type"=> "warning", "data" =>$matches[1]);
                        }
                    }

                }else{
                    $results[$i]["no_img"] = array("type"=> "warning", "data" =>$line);
                }


                if($line == "まとめ"){
                    $abstract = true;
                    $n = 0;
                    continue;
                }
                // $results[$i] = $line;    //array("type"=> "warning", "data" =>ここは共通
                $len = get_len($line);
                //見出し2の文字数が17~23
                if($len < 15){
                    $results[$i]["len_min"] = array("type"=> "warning", "data" =>"{$len}");
                }elseif($len > 24){
                    $results[$i]["len_max"] = array("type"=> "warning", "data" =>"{$len}");
                }else{
                    $results[$i]["h2_len"] = array("type"=> "debug", "data" =>"{$len}");
                }

                //指定キーワードが順番どおりに入る
                if(preg_match_all("/({$chapter["keyword"][$n][0]})|({$chapter["keyword"][$n][1]})|({$chapter["keyword"][$n][2]})/u", $line, $matches)){
                    if(
                        $matches[0][0] !== $chapter["keyword"][$chapter["number"]][0] &&
                        $matches[0][1] !== $chapter["keyword"][$chapter["number"]][1] &&
                        $matches[0][2] !== $chapter["keyword"][$chapter["number"]][2] 

                    ){
                        $results[$i]["keyword"] = array("type"=> "warning", "data" =>"指定キーワードが順番通りに入っていません");
                    }
                }
                //見出し2のキーワードの間に記号!,?,♪が入ってない
                if(preg_match("/{$chapter["keyword"][$n][0]}(.*){$chapter["keyword"][$n][1]}(.*){$chapter["keyword"][$n][2]}/u", $line, $m)){
                    if( get_len($m[1].$m[2]) > 6  ){
                        $results[$i]["between_long"] = array("type"=> "warning", "data" =>null);

                    }
                    if(preg_match("/!|\?|♪/u", $m[1].$m[2], $matches)){
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

            //空行でもない空白の場合(divタグなど)
            if($line == ""){
                if($chapter["number"] === -1){
                    // $intro_count += 0.5;
                }
                continue;
            }
            //導入文の文字数をプラス
            if($chapter["number"] === -1){
                $intro_count += get_len($line)+1;
            }


            //リストタグの場合は字数や文末をチェックしない
            if($len_check && !preg_match("/<li>|<\/li>/u", $t[$i], $matches)){
                //改行までの文字列がスマホで2行~4行に収まる
                $l = get_len($line);
                // error_log(print_r("get_len:{$line}\n"));
                if($l < 21){
                    $results[$i]["tooshort"] = array("type"=> "warning", "data" =>$l);
                    //下の行がリストタグ
                }elseif($l > 84){
                    $results[$i]["toolong"] = array("type"=> "warning", "data" =>$l);
                }
            }

            if($ending_check){
                //文末に。か？か！か♪が入っている(まとめ、空行、タイトル、テーブル以外)
                if(!preg_match("/(？|！|。|♪|\)|）)$/u", $line, $matches)){
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
                    if(preg_match("/^<strong>.*<\/strong>/u", $t[$i], $matches)){
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
                    if(preg_match("/^<span style.*(！|。)<\/span>/u", $t[$i], $matches)){
                        //Bタグとcolorタグは一行に修飾。
                        //修飾ノルマクリア
                        $norma["color"] += 1;
                    }
                }

            }



            //テーブルチェック

        }

        if(isset($chapter["keyword"][$n]) 
            && $i !== $chapter["line"][$chapter["number"]] ){
            if(preg_match_all("/({$chapter["keyword"][$n][0]})|({$chapter["keyword"][$n][1]})|({$chapter["keyword"][$n][2]})/u", $line, $matches)){
                foreach ($matches[0] as $k => $v) {
                    $norma["kwcount"][$v] += 1;
                    if(!preg_match("/(<h2>).*<\/h2>|(<h3>).*<\/h3>/", $t[$i], $matches)){
                        $t[$i] = preg_replace("/{$v}/","<span class='proofreading-item color".(array_search($v, $chapter["keyword"][$n])+1)."'
                            title=". $norma["kwcount"][$v]. "回
                            '>{$v}</span>", $t[$i]);

                    }
                }
            }
        }
        //文章の途中にリンクを入れない
        //テーブルタグを使う場合カラム名は行名はstrongを入れる、中央揃え
        //キーワード入れ込みが不足してる場合、説明
        //「ネズミにはホウ酸団子!?」ネズミにはホウ酸団子がいいと言われますよね。本当にネズミにはホウ酸団子がよいのでしょうか？ホウ酸団子ってなに？ネズミいいね

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
    $data = get_post($_GET['preview_id'] );
    $tmp = preg_split("/(　| )+/", $data->post_title);
    $len = get_len($tmp[count($tmp)-1]);
    //タイトルが28~32文字
    $type = ($len < 28 || $len > 32) ? "warning":"debug";
    $results[-1]["title_len"] = array('type' => $type, 'data' => $tmp[count($tmp)-1]."(".$len."文字)");    
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
                    if($v["type"] == "warning"){
                        $warning .="<span class='proofreading warning1'>△";
                    }else{
                        $warning .="<span class='proofreading debug1'>🌸";
                    }
                    $warning .= warning_desc($k, $v["data"]) ."<br /></span>";
                }
                $warning  .= "<p><span class='proofreading-h2'>本文</span></p>";
            }else{
                foreach ($results[$i] as $k => $v) {
                    $desc .= "\n";
                    if($v["type"] === "warning" ){
                    // if($v["type"] === "warning" || $type === "warning"){
                        $type = "warning";
                        $desc .= "△";
                    }else{
                        $desc .= "🌸";
                    }
                    $desc .= strip_tags(warning_desc($k, $v["data"])) ;
                }
                $kekka = ($type === "warning") ? "△あり":"すべてOK🎉";
                $warning .="<span class='proofreading-item {$type}1'  title='{$kekka}:{$desc}'>$t[$i]</span><br />";
            }
        }else{
            $warning .= $t[$i]."<br />";
        }
    }
    return $warning ;
}

function warning_desc($warning, $val) {
    if($warning !== "kwcheck" && !is_array($val)){
        $val = strip_tags($val);
    }
    switch ($warning) {
        case "blank":
            $result = sprintf("見出し前の改行(見出し2は2行,3なら1行)【%s行】", abs($val));
            break;
        case "h2_len":
            $result = sprintf("見出しの文字数(15~24) 【%s文字】", $val);
            break;
        case "len_max":
            $result = sprintf("タイトルが長過ぎます(20文字前後) 【%s文字】", $val);
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
            $result = sprintf("スマホで見ると1行です 21~84文字推奨【現在%s文字】△", $val);
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
            $result = sprintf("メタキーワード(5~6) </span><br />%s", $val);
            break;
        case "tag":
            $result = sprintf("タグ(3つ) </span><br />%s", $val);
            break;
        case "category":
            $result = sprintf("カテゴリー(1つ) </span><br />%s", $val);
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
            $result = sprintf("導入文ではBタグは1つのみ</span><br />%s", $val);
            break;
        case "no_strong":
            $result = sprintf("Bタグがありません</span><br />%s", $val);
            break;
        case "kanma":
            $result = sprintf("見出しに記号が入っています</span><br />【%s】", $val);
            break;
        case "no_img":
            $result = sprintf("見出しの下に画像が入っていません</span><br />【%s】", $val);
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
        default:
            $result = sprintf("{$warning} %s", $val);
            break;
    }
    return $result;
}

function get_len($string) {
    return mb_strwidth($string,'UTF-8')/2;
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
    if(isset($_GET['preview_id']) and isset($_GET['writer']) ){
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






/* 校正情報プレビューボタン表示 */
function writer_add_button() {
    
    global $post;
    // wp-admin/includes/post.phpよりコードを拝借。
    $query_args = array();
    if ( get_post_type_object( $post->post_type )->public ) {
        if ( 'publish' == $post->post_status || $user->ID != $post->post_author ) {
            // Latest content is in autosave
            $nonce = wp_create_nonce( 'post_preview_' . $post->ID );
            $query_args['preview_id'] = $post->ID;
            $query_args['preview_nonce'] = $nonce;
        }
    }
    //判別用にクリエストリング「proofreading=yes」を追加
    $query_args['preview'] = 'true';
    $query_args['writer'] = 'yes';

    $url = html_entity_decode(esc_url(add_query_arg($query_args, get_permalink($page->ID))));
?>
<script>
    (function($) {
        $('#minor-publishing-actions').append('<div class="proofreading-preview"><a id="proofreading-preview" class="button">仮添削する</a></div>');
        $(document).on('click', '#proofreading-preview', function(e) {
            e.preventDefault();
            PreviewURL = '<?php echo $url ?>';
            window.open(PreviewURL);
        });
    }(jQuery));
</script>
<?php
}

function add_kw_fields() {
    add_meta_box( 'custom_setting', '指定キーワード', 'insert_kw_fields', 'post', 'normal');
}

function insert_kw_fields() {
    global $post;
    echo '<input type="text" name="raitaa_keyword" value="'.get_post_meta($post->ID, 'raitaa_keyword', true).'" size="50" />書き方：<br /><p class="howto">見出し2-1のキーワード1-見出し2-1のキーワード2-見出し2-1のキーワード3,見出し2-2のキーワード1-見出し2-2のキーワード2-見出し2-2のキーワード3と書いてください<br />
    例:パフ-洗う-頻度,パフ-洗う-ダイソー,パフ-洗う-石鹸</p>';
}


function save_kw_fields( $post_id ) {
    if(get_post_meta($post_id, "raitaa_keyword",true) == ""){
        add_post_meta($post_id, "raitaa_keyword", $_POST['raitaa_keyword'], true);
    }elseif(!empty($_POST['raitaa_keyword'])){
        update_post_meta($post_id, 'raitaa_keyword', $_POST['raitaa_keyword'] );
    }
}
register_setting( 'weiting_setting', 'weiting_setting', 'sanitize' );

add_action( 'admin_footer-post-new.php', 'writer_add_button' );
add_action( 'admin_footer-post.php', 'writer_add_button' );
add_action('admin_menu', 'add_kw_fields');
add_action('save_post', 'save_kw_fields');
