<?php
/*
Plugin Name: Japanese Proofreading Preview
Plugin URI: 
Description: 投稿プレビュー画面にて、日本語校正支援情報を表示する（Yahoo! APIを使用)。
Author: しんさん
Version: 1.0.1
Author URI:http://mobamen.info
*/


function writing_do_checker ($content) {
    if(!isset($_GET['preview_id']) || !isset($_GET['writer']) ){
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
        $line = strip_tags($t[$i]);
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
            //見出しの?、!が半角か
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
                if($chapter["number"] === -1 ){
                    $intro_count -=1;
                    $type = ($intro_count < 300 || $intro_count > 350) ? "warning":"debug";
                    $results["meta"]["intro_count"] = array("type" => $type, "data"=>$intro_count);

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
                //章終わり。kwチェック
                $tmp = $type = "";
                foreach ($norma["kwcount"] as $k => $v) {
                // error_log(print_r("\n$t[$i] {$k}が{$v}:\n"));
                    if($v < 3){
                        $type = "warning";
                        $results[$title_line]["warning"]["kwcount"] = $norma["kwcount"];
                    }
                    $tmp .= "\n{$k}:{$v}";
                }
                $type = (count($norma["kwcount"]) !== 3) ? "warning":"debug";

                if($chapter["number"] === -1){
                    $results["meta"]["kw0"] = array("type" => $type, "data"=> count($norma["kwcount"]));
                    $results["meta"]["kwcheck"] = array("type" => $type, "data"=> "導入文:{$tmp}");
                }else{
                    $results[$title_line][$type]["kw0"] = count($norma["kwcount"]);
                    $results[$title_line]["warning"]["kwcheck"] = $tmp;
                }
                $chapter["section"] = 0;
                $chapter["number"]++;
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
                    $results[$i]["warning"]["h2_len"] = "{$len}文字";
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
            //空行でもない空白の場合(divタグなど)
            if($line == ""){
                continue;
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
                $results[$i]["warning"]["enter"] = $matches;
            }
            //文頭にですが
            if(preg_match("/^ですが/u", $line, $matches)){
                $results[$i]["warning"]["desuga"] = $matches[0];
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
                $intro_count += get_len($line)+1;
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
    $type = (get_len(strip_tags($content)) > 4000 && $norma["abst_list"] < 6  ) ? "warning":"debug";
    $results["meta"]["abst_list"] = array('type' => $type, 'data' => $norma["abst_list"]);

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
<p><span class='proofreading-h2'>サマリー</span></p>";



    foreach ($results["meta"] as $k => $v) {
        if($v["type"] == "warning"){
            $warning .="<span class='proofreading warning1'>";
        }else{
            $warning .="<span class='proofreading debug1'>";
        }
        $warning .= warning_desc($k, $v["data"]) ."<br /></span>";
    }
    $warning  .= "<p><span class='proofreading-h2'>本文</span></p>";

    for ($i=0; $i < count($t); $i++) { 
        if(isset($results[$i]["warning"])){
            $warning .="<span class='proofreading-item warning1'  title='確認:";
            foreach ($results[$i]["warning"] as $k => $v) {


                $warning .= "\n ".strip_tags(warning_desc($k, $v)) ;
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
        case "intro_count":
            $result = sprintf("導入文の文字数(300±) </span><br />%s文字", $val);
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


function writer_css () {
    //プレビュー画面かつ「校正情報プレビュー」ボタンから呼ばれた時にのみ処理を実施
    //is_preview()が正常に動作しないケースに遭遇したため、クエリストリングでプレビュー状態かどうか判断しています。
    if(isset($_GET['preview_id']) and isset($_GET['writer']) ){
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
if( isset($_GET['writer']) ){
    add_action('wp_enqueue_scripts', 'writer_css');
    add_filter('the_content','writing_do_checker');
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

function add_book_fields() {
    add_meta_box( 'book_setting', '本の情報', 'insert_book_fields', 'post', 'normal');
}

function insert_book_fields() {
    global $post;
    echo 'キーワード： <input type="text" name="writer_keyword" value="'.get_post_meta($post->ID, 'writer_keyword', true).'" size="50" />書き方：<br /><p class="howto">見出し2-1のキーワード1-見出し2-1のキーワード2-見出し2-1のキーワード3,見出し2-2のキーワード1-見出し2-2のキーワード2-見出し2-2のキーワード3と書いてください<br />
    例:パフ-洗う-頻度,パフ-洗う-ダイソー,パフ-洗う-石鹸</p>';
}

function save_book_fields( $post_id ) {
    if(!empty($_POST['writer_keyword'])){
        update_post_meta($post_id, 'writer_keyword', $_POST['writer_keyword'] ); //値を保存
    }else{ //題名未入力の場合
        delete_post_meta($post_id, 'writer_keyword'); 
    }
}
register_setting( 'weiting_setting', 'weiting_setting', 'sanitize' );
add_action('save_post', 'save_book_fields');

add_action( 'admin_footer-post-new.php', 'writer_add_button' );
add_action( 'admin_footer-post.php', 'writer_add_button' );
add_action('admin_menu', 'add_book_fields');
