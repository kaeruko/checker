<?php
/*
Plugin Name: Japanese Proofreading Preview
Plugin URI: 
Description: 投稿プレビュー画面にて、日本語校正支援情報を表示する（Yahoo! APIを使用)。
Author: しんさん
Version: 1.0.1
Author URI:http://mobamen.info
*/

//2次元配列の2次元目の配列の値でソートをする関数
function yproofreading_sortArrayByKey( &$array, $sortKey, $sortType = SORT_ASC ) {
    $tmpArray = array();
    foreach ( $array as $key => $row ) {
        $tmpArray[$key] = $row[$sortKey];
    }
    array_multisort( $tmpArray, $sortType, $array );
    unset( $tmpArray );
}

//UTF-8のマルチバイト文字列を1文字ずつ分解する関数
function yproofreading_mb_str_split($str, $split_len = 1) {
    mb_internal_encoding('UTF-8');
    mb_regex_encoding('UTF-8');
    if ($split_len <= 0) {
        $split_len = 1;
    }
    $strlen = mb_strlen($str, 'UTF-8');
    $ret    = array();
    for ($i = 0; $i < $strlen; $i += $split_len) {
        $ret[ ] = mb_substr($str, $i, $split_len);
    }
    return $ret;
}

/*
* Yahoo API 設定画面
* ※無名関数を排除するために読みづらい記述になってしまっているので修正を検討したい。
*/
function yproofreading_print_setting_guide_link () {
    echo '<a href="http://mobamen.info/wordpress_proofreading#Japanese_Proofreading_Preview-3" target="_blank">設定方法の詳細はこちらから</a>';
}
function yproofreading_print_appid_input_box () {
    echo '<input name="yahoo_appid" id="yahoo_appid" type="text" class="code" value="' . esc_attr(get_option('yahoo_appid')) . '" /></br>';
}
function yproofreading_print_nofilter_desc () {
    echo '<p>文章校正にて<u>指摘対象外</u>とするものにチェックを入れて下さい。</p>';
    //横着なやり方だが、BODY内でcssを指定する。
    echo '<style>.form-table{width: 70%;}.form-table th {padding: 0px 0;width: 100px;}.form-table td {width: 1px;white-space: nowrap;margin-bottom: 0;padding: 0 0;}</style>';
}
function yproofreading_print_nofilter_chek_box ($filter_num) {
    $yahoo_filters = array(
        '<b>二重否定</b></td><td>例：<i>聞かなくはない</i>' ,
        '<b>助詞不足の可能性あり</b></td><td>例：<i>学校行く</i>' ,
        '<b>冗長表現</b></td><td>例：<i>ことができます</i>' ,
        '<b>略語</b></td><td>例：<i>ADSL→非対称デジタル加入者線(ADSL)</i>'
    );
    
    $option_id = 'yahoo_nofilter' . $filter_num ;
    echo '<input type="checkbox" id="' . $option_id . '" name="' . $option_id . '"';
    checked(get_option($option_id), 1);
    echo ' value="1" />' . $yahoo_filters[$filter_num -1 ] . '';
}
function yproofreading_print_nofilter_chek_box1 () {yproofreading_print_nofilter_chek_box ( 1 );};
function yproofreading_print_nofilter_chek_box2 () {yproofreading_print_nofilter_chek_box ( 2 );};
function yproofreading_print_nofilter_chek_box3 () {yproofreading_print_nofilter_chek_box ( 3 );};
function yproofreading_print_nofilter_chek_box4 () {yproofreading_print_nofilter_chek_box ( 4 );};
function yproofreading_print_nofilter_chek_box5 () {yproofreading_print_nofilter_chek_box ( 5 );};
function yproofreading_print_nofilter_chek_box6 () {yproofreading_print_nofilter_chek_box ( 6 );};
function yproofreading_print_nofilter_chek_box7 () {yproofreading_print_nofilter_chek_box ( 7 );};
function yproofreading_print_nofilter_chek_box8 () {yproofreading_print_nofilter_chek_box ( 8 );};
function yproofreading_print_nofilter_chek_box9 () {yproofreading_print_nofilter_chek_box ( 9 );};
function yproofreading_print_nofilter_chek_box10 () {yproofreading_print_nofilter_chek_box ( 10 );};
function yproofreading_print_nofilter_chek_box11 () {yproofreading_print_nofilter_chek_box ( 11 );};
function yproofreading_print_nofilter_chek_box12 () {yproofreading_print_nofilter_chek_box ( 12 );};
function yproofreading_print_nofilter_chek_box13 () {yproofreading_print_nofilter_chek_box ( 13 );};
function yproofreading_print_nofilter_chek_box14 () {yproofreading_print_nofilter_chek_box ( 14 );};
function yproofreading_print_nofilter_chek_box15 () {yproofreading_print_nofilter_chek_box ( 15 );};
function yproofreading_print_nofilter_chek_box16 () {yproofreading_print_nofilter_chek_box ( 16 );};
function yproofreading_print_nofilter_chek_box17 () {yproofreading_print_nofilter_chek_box ( 17 );};
function yproofreading_register_settings (){
    //Yahoo API App IDの設定用
    add_settings_section(
        'yahoo_api_setting_section',
        'Yahoo API 設定',
        'yproofreading_print_setting_guide_link',
        'yproofreading'
    );
    add_settings_field(
        "yahoo_appid",
        "Yahoo API ID",
        'yproofreading_print_appid_input_box',
        'yproofreading',
        'yahoo_api_setting_section'
    );
    register_setting('yproofreading_group', 'yahoo_appid', 'wp_filter_nohtml_kses');
    //除外用フィルター用
    add_settings_section(
        'yahoo_nofilter_setting_section',
        '指摘除外設定',
        'yproofreading_print_nofilter_desc',
        'yproofreading'
    );
    
    for ($filter_num = 1 ; $filter_num <= 17 ; $filter_num++){
        $option_id = 'yahoo_nofilter' . $filter_num ;
        add_settings_field(
           $option_id,
           "",
           'yproofreading_print_nofilter_chek_box' . $filter_num,
           'yproofreading',
           'yahoo_nofilter_setting_section'
        );
        
        register_setting('yproofreading_group', $option_id);
    }
}
add_action('admin_init', 'yproofreading_register_settings');

/*
* 設定画面にYahoo API 設定画面を追加
* ※マルチサイト対応すべきか検討したい。
* ※無名関数を排除するために読みづらい記述になってしまっているので修正を検討したい。
*/
function yproofreading_print_options_form () {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <form action="options.php" method="post">
            <?php settings_fields('yproofreading_group'); ?>
            <?php do_settings_sections('yproofreading'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
function yproofreading_register_link_to_setting_page () {
    add_options_page('校正支援', '校正支援', 'manage_options', 'yproofreading', 'yproofreading_print_options_form' );
}
add_action('admin_menu', 'yproofreading_register_link_to_setting_page' );

/*
*プラグイン一覧に設定画面へのリンクと設定方法解説ページへのリンクを追加する
* ※無名関数を排除するために読みづらい記述になったので修正を検討したい。
*/
function yproofreading_append_custom_links_to_pluginslist( $links ) {
    array_unshift( $links, '<a href="http://mobamen.info/wordpress_proofreading#Japanese_Proofreading_Preview-3" target="_blank">設定方法</a>');
    array_unshift( $links, '<a href="options-general.php?page=yproofreading">設定</a>');
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__) ,'yproofreading_append_custom_links_to_pluginslist' );

/*
* Yahoo APIに渡すnofilter属性を作成する。
*/
function yproofreading_build_nofilter() {
    $nofilter ="";    
    return $nofilter;
}

//Yahoo APIに文章校正のリクエストを投げて結果をSimpleXMLElementで返す関数
function yproofreading_get_kousei_result($sentence) {
}


function writing_do_checker ($content) {
    if(!isset($_GET['preview_id']) || !isset($_GET['proofreading']) ){
        return;
    }

    $content = preg_replace( '/<p>|<\/p>/msi','',$content);

    $t = preg_split("/[\n|\r,]+/", $content);

    $chapter["keyword"] = array(
        0 => array("パフ","洗う","頻度"),
        1 => array("パフ","洗う","ダイソー"),
        2 => array("パフ","洗う","食器用洗剤"),
        3 => array("パフ","洗う","石鹸"),
        4 => array("パフ","洗う","クレンジングオイル"),
    );
    $chapter["number"] = -1;
    $chapter["section"] = 0;
    $chapter["line"][-1] = 0;
    $norma = array("kwcount"=> array(),"strong"=> 0, "color"=> 0 , "abst_list" => 0);
    $abstract = false;
    $results = array();
    $id = get_the_ID();
    $type = "";
    //メタディスクリプション
    $the_page_meta_description = (get_post_meta($id, 'the_page_meta_description', true));
    $tmp = get_len($the_page_meta_description);
    $type = ($tmp > 120 || $tmp < 100) ? "warning":"debug";
    $results["meta"]["meta_desc"] = array('type' => $type, 'data' => "{$the_page_meta_description}({$tmp}文字)");
    //メタキーワード
    $metakw = explode(",", get_post_meta($id, 'the_page_meta_keywords', true));
    $type = (count($metakw) < 5) ? "warning":"debug";
    $results["meta"]["metakw"] = array('type' => $type, 'data' => (implode("-", $metakw))."(".(count($metakw)).")" );
    //メタタグ
    $tags = array_map(function($tag) { return $tag->name; },get_the_tags());
    $type = (count($tags) !== 3) ? "warning":"debug";
    $results["meta"]["tag"] = array('type' => $type, 'data' => implode("-", $tags) . "(". (count($tags)).")");
    //カテゴリー
    $category = array_map(function($tag) { return $tag->name; },get_the_category());
    $type = (count($category) !== 1) ? "warning":"debug";
    $results["meta"]["category"] = array('type' => $type, 'data' =>(implode("-", $category)));


    $n = 0;
    $intro_count = 0;
    $tcount = count($t);
    for ($i=0; $i < count($t); $i++) { 
        //空行の場合、下にhrか空行があるかチェック
        if(preg_match("/&nbsp;/u", $t[$i], $matches)){
            //これだと3空行があってもスルーされるな
            if(!preg_match("/<h2>|<h3>|speech-balloon|&nbsp;/", $t[$i+1], $matches)){
                $results[$i]["warning"]["bad_blank"] = $t[$i+1];
            }
            continue;
        }
        //ボックスタグの場合リストタグと併用
        if(preg_match("/information-box/u", $t[$i], $matches)){
            //タグ内全部見るべきだろうけど下だけ
            if($t[$i+1] !=="<ul>"){
                // error_log(print_r($t[$i+1]));
                $results[$i]["warning"]["no_list"] = $t[$i];
            }
        }
        //数字は全て半角
        if(preg_match("/[０-９]/u", $t[$i], $matches)){
            $results[$i]["warning"]["zenkaku_num"] = $t[$i];
        }
        // 見出し<h2><h3>か最後まできたらキーワードチェック
        if(preg_match("/(<h2>).*<\/h2>|(<h3>).*<\/h3>/", $t[$i], $matches)
|| $tcount == ($i+1)
    ){
            $line = strip_tags($t[$i]);
            //見出しに、はつけない
            if(preg_match("/、/u", $line, $_m)){
                $results[$i]["warning"]["kanma"] = $_m[0];
            }
            //見出し2の?、!が半角か
            if(preg_match("/？|！|♪/u", $line, $_m)){
                $results[$i]["warning"]["zenkaku_kigo"] = $_m[0];
            }

            if($t[$i-1] !== "&nbsp;"){
                $results[$i]["warning"]["no_blank"] = $line;
            }

            if(@$matches[1] === "<h2>" || $tcount == ($i+1)){
                //見出し2の添字チェック
                $chapter["line"][$chapter["number"]+1] = $i;
                // 前章が終わった。前節の数チェック(見出し3は2つ以上入れる)
                $title_line = $chapter["line"][$chapter["number"]];

                if($chapter["section"] === 1){
                    $results[$title_line]["warning"]["section"] = $chapter["section"];
                // error_log(print_r("\n{$t[$title_line]}\n{$t[$i-2]}\n"));
                }
                //導入文の文字数が300~350
                // error_log(print_r("intro_count:{$intro_count}"));
                if($chapter["number"] === -1 
                    && $intro_count < 300 || 
                     $intro_count > 350){
                    $results[0]["warning"]["intro_count"] = $intro_count;
                }else{
                    $results[0]["debug"]["intro_count"] = "OK:{$intro_count}文字";

                }
                if(!$norma["color"] === 0  ){
                    // error_log(print_r("{$t[$title_line]}\n"));
                    $results[$title_line]["warning"]["notag"] = $norma["color"];
                }
                if(!$norma["strong"] === 0  ){
                    // error_log(print_r("{$t[$title_line]}\n"));
                    $results[$title_line]["warning"]["notag"] = $norma["strong"];
                }


                // error_log(print_r("chap:{$chapter['number']} n:{$n}"));
                $chapter["section"] = 0;
                $chapter["number"]++;
                $n = $chapter["number"];
                if($chapter["number"] < 0){
                    $n = 0;
                }
                //章終わり。kwチェック
                $tmp = "";
                foreach ($norma["kwcount"] as $k => $v) {
                // error_log(print_r("\n$t[$i] {$k}が{$v}:\n"));

                    if(count($norma["kwcount"]) !== 3){
                        $results[$title_line]["warning"]["kw0"] = $norma["kwcount"];
                    }
                    if($v < 3){
                        $results[$title_line]["warning"]["kwcount"] = $norma["kwcount"];
                    }
                    $tmp .= "\n{$k}:{$v}";
                }
                $results[$title_line]["warning"]["kwcheck"] = $tmp;
                $norma = array("kwcount"=> array(),"strong"=> 0, "color"=> 0 , "abst_list" => 0);
                if($tcount == ($i+1)){
                    continue;
                }
                //直前に空行2行ある
                if($t[$i-2] !== "&nbsp;"){
                // error_log(print_r("\n{$t[$i-1]}\n{$t[$i-2]}\n"));
                    $results[$title_line]["warning"]["no_blank"] = $t[$i-2];
                }


                if($line == "まとめ"){
                    $abstract = true;
                    $n = 0;
                    continue;
                }
                //見出し2(まとめも)の下に画像がある
                if(preg_match("/src=.+?\"(.*?) \"?/x", $t[$i+1], $matches)){
                    //画像のサイズが横300形式がjpg
                    if(substr($matches[0], -4,3) !== "jpg" ){
                        $results[$i]["warning"]["img_ext"] = substr($matches[1], 3);
                    }

                    //横サイズが300
                    if(preg_match("/width\=\"([0-9]+) /x", $t[$i+1], $matches)){
                        if($matches[1] !== "300"){
                            $results[$i]["warning"]["img_width"] = $matches[1];
                        }
                    }

                }else{
                    $results[$i]["warning"]["no_img"] = $line;
                }

                // $results[$i]["contents"] = $line;    //ここは共通
                $len = get_len($line);
                //見出し2の文字数が17~23
                if($len < 17){
                    $results[$i]["warning"]["len_min"] = "{$len}文字";
                }elseif($len > 23){
                    $results[$i]["warning"]["len_max"] = "{$len}文字";
                }else{
                    $results[$i]["debug"]["len_max"] = "{$len}文字";
                }

                //指定キーワードが順番どおりに入る
                if(preg_match_all("/({$chapter["keyword"][$n][0]})|({$chapter["keyword"][$n][1]})|({$chapter["keyword"][$n][2]})/u", $line, $matches)){
                    if(
                        $matches[0][0] !== $chapter["keyword"][$chapter["number"]][0] &&
                        $matches[0][1] !== $chapter["keyword"][$chapter["number"]][1] &&
                        $matches[0][2] !== $chapter["keyword"][$chapter["number"]][2] 

                    ){
                        $results[$i]["warning"]["keyword"] = implode($chapter["keyword"][$chapter["number"]],",")."が順番通りに入っていません";
                    }
                }
                //見出し2のキーワードの間に記号!,?,♪が入ってない
                if(preg_match_all("/{$chapter["keyword"][$n][0]}(.*){$chapter["keyword"][$n][1]}(.*){$chapter["keyword"][$n][2]}/u", $line, $m)){
                    if(preg_match("/!|\?|♪/u", $m[1][0].$m[2][0], $matches)){
                        $results[$i]["warning"]["between"] = implode($matches, "");
                    }
                }
            }elseif($matches[2] == "<h3>"){
                $chapter["section"]++;
            }

        }else{
            //まとめの箇条書きカウント
            if($abstract && preg_match("/<li>/u", $t[$i], $matches)){
            // error_log(print_r("{$t[$i]} {$norma['abst_list']}\n"));
                $norma["abst_list"] += 1;
            }

            //shift+enter
            if(preg_match("/^<div>.*<\/div>$/u", $t[$i], $matches)){
                $results[$i]["warning"]["enter"] = $matches;
            }
            //文頭にですが
            if(preg_match("/^ですが/u", $t[$i], $matches)){
                $results[$i]["warning"]["desuga"] = $matches[0];
            }

            $line = strip_tags($t[$i]);
            //空行でもない空白の場合(divタグなど)
            if($line == ""){
                continue;
            }
            //文中で!?は使わない
            if(preg_match("/[!|?]/u", $line, $matches)){
                $results[$i]["warning"]["hankaku_kigo"] = $matches[0];
            }

            if(preg_match_all("/({$chapter["keyword"][$n][0]})|({$chapter["keyword"][$n][1]})|({$chapter["keyword"][$n][2]})/u", $line, $matches)){

                foreach ($matches[0] as $k => $v) {
                    // error_log(print_r($v));
                    @$norma["kwcount"][$v] += 1;
                    $t[$i] = preg_replace("/{$v}/","<span class='proofreading-item color".(array_search($v, $chapter["keyword"][$n])+1)."'
                        title=". $norma["kwcount"][$v]. "回
                        '>{$v}</span>", $t[$i]);
                }
                // error_log(print_r("{$n} {$t[$i]} "));
                // error_log(print_r($matches[0]));
            }
            if(preg_match("/<strong>/u", $t[$i], $matches)){
                //まとめの場合は警告
                if($abstract){
                    $results[$i]["warning"]["abst_tag"] = $matches[1];
                //すでにある場合は警告
                }elseif($norma["strong"] > 0){
                    $results[$i]["warning"]["too_strong"] = $matches[1];
                }else{
                    if(preg_match("/^<strong>.*<\/strong>$/u", $t[$i], $matches)){
                        //Bタグとcolorタグは一行に修飾。
                        //修飾ノルマクリア
                        $norma["strong"] += 1;
                    }
                }

            }
            if(preg_match("/<span style=\"color:/u", $t[$i], $matches)){

                //まとめの場合は警告
                if($abstract){
                    $results[$i]["warning"]["abst_tag"] = $matches[0];
                }else{
                // if(preg_match("/src=.+?\"(.*?)\\\ /x", $t[$i+1], $matches)){
                    if(preg_match("/^<span style.*<\/span>$/u", $t[$i], $matches)){
                        //Bタグとcolorタグは一行に修飾。
                        //修飾ノルマクリア
                        $norma["color"] += 1;
                    }
                }

            }

            //導入文の文字数をプラス
            if($chapter["number"] === -1){
                $intro_count += get_len($line);
            }
            //リストタグの場合は字数や文末をチェックしない
            if(!preg_match("/<li>|<\/li>/u", $t[$i], $matches)){
                //文末に。か？か！か♪が入っている(まとめ、空行、タイトル、テーブル以外)
                if(!preg_match("/(？|！|。|♪|\)|）)$/u", $line, $matches)){
                    //下の行がリストタグ

                    preg_match("/.$/u", $line, $matches);
                    $results[$i]["warning"]["ending"] = $matches[0];
                }
                //改行までの文字列がスマホで2行~4行に収まる
                $l = get_len($line);
                // error_log(print_r("get_len:{$line}\n"));
                if($l < 21){
                    $results[$i]["warning"]["tooshort"] = $l;
                    //下の行がリストタグ
                }elseif($l > 84){
                    $results[$i]["warning"]["toolong"] = $l;
                }
            }

            //テーブルチェック

        }

        //文章の途中にリンクを入れない
        //テーブルタグを使う場合カラム名は行名はstrongを入れる、中央揃え
        //キーワード入れ込みが不足してる場合、説明
        //「ネズミにはホウ酸団子!?」ネズミにはホウ酸団子がいいと言われますよね。本当にネズミにはホウ酸団子がよいのでしょうか？ホウ酸団子ってなに？ネズミいいね

        //皆さんはダメ
        //autokanaで。中身を見れば英字になってるところあるはず画像名生成　zuでもduでもでるのか
        //2重装飾は控える
        //word校正はしましたか？、コピペチェックは%でしたか？(リンクを付ける)メタディスクリプションの文字数115~120、メタキーワードが5,6、メタキーワードのうち3はキーワード、文字数は何文字でした。大丈夫ですか？添削依頼表をセットし、添削が終わったら黄色を塗ろう(依頼表へのリンクGoogleスプレッドシートAPIでできるかも)。リビジョンを更新しましょう、編集画面を閉じましょう

    }
    //まとめのリストタグが4000文字を超える場合6~8
    if(get_len(strip_tags($content)) > 4000
        && $norma["abst_list"] < 6){
        $results[$i]["warning"]["abst_list"] = $norma["abst_list"];
    }

    //見出し2はまとめいれて4つ以上           
    $type = ($chapter["number"] < 3) ? "warning":"debug";
    $results["meta"]["chap_no"] = array('type' => $type, 'data' =>$chapter["number"]);

    // $a = get_post_meta_by_id($_GET['preview_id']);
    // $b = get_post_meta($a["meta_id"]);
    // error_log(print_r( get_post($_GET['preview_id'] )->post_title));
    $data = get_post($_GET['preview_id'] );
    $tmp = explode(" ", $data->post_title);
    $len = get_len($tmp[count($tmp)-1]);
    //タイトルが28~32文字
    $type = ($len < 28 || $len > 32) ? "warning":"debug";
    $results["meta"]["title_len"] = array('type' => $type, 'data' => $tmp[count($tmp)-1]."(".$len."文字)");

    //ステータスチェック
    //パーマリンク

    //アイキャッチがある
    //カテゴリは1つ。とタグの設定

    $warning  = "<div class='proofreading-result'>
<div class='proofreading-summary'>
<p><span class='proofreading-h2'>チェッカー</span></p>";



    foreach ($results["meta"] as $k => $v) {
        if($v["type"] == "warning"){
            $warning .="<span class='proofreading-h3 warning1'>";
        }else{
            $warning .="<span class='proofreading-h3 debug1'>";
        }
        $warning .= get_warning($k, $v["data"]) ."<br /></span>";
    }

    for ($i=0; $i < count($t); $i++) { 
        // if($v["type"] == "warning"){
            // $warning .="<span class='proofreading-item warning1'  title='";
        // }else{
            // $warning .="<span class='proofreading-item' title='";
        // }
            // $warning .= "'>$t[$i]</span>";
        // $warning .= get_warning($k, $v["data"]) ."<br /></span>";



        if(isset($results[$i]["warning"])){
            $warning .="<span class='proofreading-item warning1'  title='確認:";
            foreach ($results[$i]["warning"] as $k => $v) {


                $warning .= "\n ".get_warning($k, $v) ;
            }
            $warning .= "'>$t[$i]</span>";
        }else{
            $warning .= $t[$i];
        }
        $warning .= "<br />";
    }
    $ret .= "タイトルの文字数:{$len}";
    $ret .= $tmp[count($tmp)-1];
    return $warning ;
}

function get_warning($warning, $val) {
    $result = "";
    switch ($warning) {
        case "no_blank":
        case "bad_blank":
            $result = warning_desc($warning, $val);
            break;        
        default:
            $result = warning_desc($warning, $val);
            break;
    }
    return $result;
}

function warning_desc($warning, $val) {
    $val = strip_tags($val);
    switch ($warning) {
        case "no_blank":
            $result = sprintf("改行がありません %s", $val);
            break;
        case "bad_blank":
            $result = sprintf("※見出しや吹き出しの前以外で改行が入っています 【%s】", $val);
            break;
        case "hankaku_kigo":
            $result = sprintf("※見出し以外で半角の!や?が使われています 【%s】", $val);
            break;
        
        case "ending":
            $result = sprintf("※？ ！ 。 ♪ ) 以外の文末です 【%s】", $val);
            break;
        case "tooshort":
            $result = sprintf("※スマホで見ると1行です 21~84文字推奨【現在%s文字】", $val);
            break;
        case "toolong":
            $result = sprintf("※スマホで見ると4行以上です 21~84文字推奨【現在%s文字】", $val);
            break;
        case "kwcount":
        case "kwcheck":
            $result = sprintf("※キーワード埋め込み %s", $val);
            break;
        default:
            $result = sprintf("{$warning} %s", $val);
            break;
    }
    return $result;
}

function get_len($string) {
    return floor(strlen($string)/3) + (strlen($string) % 3 * 0.5);
}









/*
*cssリンクをヘッダーに追加する
*/


function yproofreading_enqueue_css () {
    //プレビュー画面かつ「校正情報プレビュー」ボタンから呼ばれた時にのみ処理を実施
    //is_preview()が正常に動作しないケースに遭遇したため、クエリストリングでプレビュー状態かどうか判断しています。
    if(isset($_GET['preview_id']) and isset($_GET['proofreading']) ){
        wp_register_style(
            'proofreading',
            plugins_url('css/proofreading.css', __FILE__),
            array(),
            1.0,
            'all'
        );
        wp_enqueue_style('proofreading');
    }
}
//クエリストリングより文章構成支援のオンオフを判定し、必用な時のみアクションとフィルターをフックする。
//※条件を絞らないとフックされる機会が多すぎるのでは無いかと考えたため。
if( isset($_GET['proofreading']) ){
    add_action('wp_enqueue_scripts', 'yproofreading_enqueue_css');
    add_filter('the_content','writing_do_checker');
}






















/* 校正情報プレビューボタン表示 */
function yproofreading_add_proofreading_preview_button() {
    
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
    $query_args['proofreading'] = 'yes';
    $url = html_entity_decode(esc_url(add_query_arg($query_args, get_permalink($page->ID))));
?>
<script>
    (function($) {
        $('#minor-publishing-actions').append('<div class="proofreading-preview"><a id="proofreading-preview" class="button">チェッカー付きプレビュー</a></div>');
        $(document).on('click', '#proofreading-preview', function(e) {
            e.preventDefault();
            PreviewURL = '<?php echo $url ?>';
            window.open(PreviewURL);
        });
    }(jQuery));
</script>
<?php
}
register_setting( 'weiting_setting', 'weiting_setting', 'sanitize' );

add_action('admin_init', 'your_function');

function your_function(){
add_settings_field( 'api_key', 'CCDのAPIキー', 'api_key_callback', 'weiting_setting', 'ccd_setting_section_id' );
}  

add_action( 'admin_footer-post-new.php', 'yproofreading_add_proofreading_preview_button' );
add_action( 'admin_footer-post.php', 'yproofreading_add_proofreading_preview_button' );
