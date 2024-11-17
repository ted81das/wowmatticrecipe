<?php
/**
Plugin Name: Aiomatic - Automatic AI Content Writer, Editor, Chatbot & AI Toolkit
Plugin URI: //1.envato.market/aiomatic
Description: All in one AI plugin for content creation, content editing, chatbots and many more extra features
Author: CodeRevolution
Version: 2.2.5
Author URI: //coderevolution.ro
License: Commercial. For personal use only. Not to give away or resell.
Text Domain: aiomatic-automatic-ai-content-writer
*/
/*  
Copyright 2016 - 2024 CodeRevolution
*/
defined('ABSPATH') or die();

const AIOMATIC_MAJOR_VERSION = '2.2.5';
// At the top of aiomatic.php with other plugin information
define('AIOMATIC_VERSION', '1.0.0'); // Use your actual plugin version

require_once (dirname(__FILE__) . "/aiomatic-constants.php");
require_once (dirname(__FILE__) . "/res/other/plugin-dash.php");
require_once (dirname(__FILE__) . "/aiomatic-helpers.php");
require_once (dirname(__FILE__) . "/aiomatic-assistants-file.php");
require_once (dirname(__FILE__) . "/aiomatic-batches-file.php");
require_once (dirname(__FILE__) . "/aiomatic-shortcodes-file.php");
require_once (dirname(__FILE__) . "/aiomatic-ajax-actions.php");
require_once (dirname(__FILE__) . "/aiomatic-spin-translate.php");
require_once (dirname(__FILE__) . "/aiomatic-do-post.php");
require_once (dirname(__FILE__) . "/aiomatic-rules.php");
require_once (dirname(__FILE__) . "/aiomatic-streaming.php");
require_once (dirname(__FILE__) . "/aiomatic-media-expirator.php");
require_once(dirname(__FILE__) . "/aiomatic-rest-api.php");

use Aws\S3\S3Client;
use \Eventviva\ImageResize;
use AiomaticOpenAI\OpenAi\OpenAi;
$omni_files = array();
function aiomatic_get_version() {
    $plugin_data = get_file_data( __FILE__  , array('Version' => 'Version'), false);
    return $plugin_data['Version'];
}
function aiomatic_add_custom_bulk_action($actions) 
{
    $actions['aiomatic_embeddings'] = esc_html__('[Aiomatic] Create Embeddings', 'aiomatic-automatic-ai-content-writer');
    $actions['aiomatic_processing'] = esc_html__('[Aiomatic] Run AI Content Editor', 'aiomatic-automatic-ai-content-writer');
    $actions['aiomatic_edited'] = esc_html__('[Aiomatic] Mark As Edited', 'aiomatic-automatic-ai-content-writer');
    $actions['aiomatic_not_edited'] = esc_html__('[Aiomatic] Mark As Not Edited', 'aiomatic-automatic-ai-content-writer');
    return $actions;
}
add_filter( 'page_template', 'aiomatic_page_template' );
function aiomatic_page_template( $page_template )
{
    global $post;
    if (is_page( 'chatbot-embedding-ai-gateway' ) && $post->ID == get_option('aiomatic_chat_page_id'))
    {
        $page_template = dirname( __FILE__ ) . '/templates/aiomatic-chat-template.php';
    }
    return $page_template;
}

function aiomatic_custom_page_template( $template ) 
{
    global $post;
    if ( is_singular() && 'aiomatic_remote_chat' == $post->post_type ) 
    {
        $template = dirname( __FILE__ ) . '/templates/aiomatic-chat-template.php';
    }
    return $template;
}
add_filter( 'single_template', 'aiomatic_custom_page_template' );

function aiomatic_wpcli_add( $args ) 
{
    if ( defined( 'WP_CLI' ) && WP_CLI )
    {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
        {
            if(count($args) < 2)
            {
                WP_CLI::error( 'Parameters missing. Usage: <aimodel> <aiprompt>');
            }
            list($model, $prompt) = $args;
            $all_models = aiomatic_get_all_models(true);
            if(!in_array($model, $all_models))
            {
                $model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
            }
            $query_token_count = count(aiomatic_encode($prompt));
            $max_tokens = aiomatic_get_max_tokens($model);
            $available_tokens = aiomatic_compute_available_tokens($model, $max_tokens, $prompt, $query_token_count);
            if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
            {
                $string_len = strlen($prompt);
                $string_len = $string_len / 2;
                $string_len = intval(0 - $string_len);
                $prompt = aiomatic_substr($prompt, 0, $string_len);
                $prompt = trim($prompt);
                $query_token_count = count(aiomatic_encode($prompt));
                $available_tokens = $max_tokens - $query_token_count;
            }
            if(!empty($prompt))
            {
                $GLOBALS['aiomatic_stats'] = new Aiomatic_Statistics();
                $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
                $appids = array_filter($appids);
                $token = $appids[array_rand($appids)];
                $token = apply_filters('aiomatic_openai_api_key', $token);
                $thread_id = '';
                $aierror = '';
                $finish_reason = '';
                $generated_text = aiomatic_generate_text($token, $model, $prompt, $available_tokens, 1, 1, 0, 0, false, 'wpcli', 0, $finish_reason, $aierror, false, false, false, '', '', 'user', '', $thread_id, '', 'disabled', '', false, false);
                if($generated_text === false)
                {
                    WP_CLI::error( 'Failed to generate the AI reply, error: ' . $aierror);
                }
                else
                {
                    $generated_text = aiomatic_sanitize_ai_result($generated_text);
                    if(empty($generated_text))
                    {
                        WP_CLI::error( 'Empty AI response returned!');
                    }
                    else
                    {
                        WP_CLI::log($generated_text);
                    }
                }
            }
            else
            {
                WP_CLI::error( 'Empty AI prompt provided!' );
            }
        }
        else
        {
            WP_CLI::error( 'You need to add an AI API key in the Aiomatic plugin\'s settings for this to work!' );
        }
    }
    else
    {
        WP_CLI::error( 'WP_CLI not found!' );
    }
    exit;
}

function aiomatic_add_custom_column_to_posts($columns) 
{
    $columns['aiomatic_edited'] = 'Aiomatic Edited';
    return $columns;
}

function aiomatic_custom_column_content($column_name, $post_id) 
{
    if ($column_name == 'aiomatic_edited') 
    {
        $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
        if (isset($aiomatic_Spinner_Settings['custom_name']) && trim($aiomatic_Spinner_Settings['custom_name']) != '')
        {
            $custom_name = trim($aiomatic_Spinner_Settings['custom_name']);
            $custom_name = str_replace('%%current_date%%', date("Y-m-d"), $custom_name);
        }
        else
        {
            $custom_name = 'aiomatic_published';
        }
        $value = get_post_meta($post_id, $custom_name, true);
        if ($value == 'pub')
        {
            echo esc_html__('Yes', 'aiomatic-automatic-ai-content-writer');
        }
        else
        {
            echo esc_html__('No', 'aiomatic-automatic-ai-content-writer');
        }
    }
}

function aiomatic_custom_column_sortable($columns) 
{
    $columns['aiomatic_edited'] = 'aiomatic_edited';
    return $columns;
}

function aiomatic_custom_column_orderby($query) 
{
    if (!is_admin() || !$query->is_main_query()) 
    {
        return;
    }

    if ($query->get('orderby') == 'aiomatic_edited') 
    {
        $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
        if (isset($aiomatic_Spinner_Settings['custom_name']) && trim($aiomatic_Spinner_Settings['custom_name']) != '')
        {
            $custom_name = trim($aiomatic_Spinner_Settings['custom_name']);
            $custom_name = str_replace('%%current_date%%', date("Y-m-d"), $custom_name);
        }
        else
        {
            $custom_name = 'aiomatic_published';
        }
        $query->set('meta_query', array(
            'relation' => 'OR',
            array(
                'key' => $custom_name,
                'compare' => 'EXISTS',
            )
        ));
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'aiomatic_custom_column_orderby');
add_filter('manage_edit-post_sortable_columns', 'aiomatic_custom_column_sortable');
add_action('manage_posts_custom_column', 'aiomatic_custom_column_content', 10, 2);
add_filter('manage_posts_columns', 'aiomatic_add_custom_column_to_posts');

function aiomatic_add_bulk_actions_to_all_post_types() 
{
    $post_types = get_post_types('', 'names');
    foreach ($post_types as $post_type) 
    {
        if(in_array($post_type, AIOMATIC_EXCEPTED_POST_TYPES_FROM_EDITING))
        {
            continue;
        }
        add_filter("bulk_actions-edit-{$post_type}", 'aiomatic_add_custom_bulk_action');
        add_filter("handle_bulk_actions-edit-{$post_type}", 'aiomatic_custom_bulk_action_handler', 10, 3);
    }
}

function aiomatic_custom_bulk_action_handler($redirect_to, $action, $post_ids) 
{
    if ($action === 'aiomatic_embeddings') 
    {
        require_once(dirname(__FILE__) . "/res/Embeddings.php");
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
        {
            $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
            $appids = array_filter($appids);
            $token = $appids[array_rand($appids)];
            $token = apply_filters('aiomatic_openai_api_key', $token);
            $embdedding = new Aiomatic_Embeddings($token);
            foreach($post_ids as $pid)
            {
                if (isset($aiomatic_Main_Settings['bulk_embedding_template']) && trim($aiomatic_Main_Settings['bulk_embedding_template']) != '')
                {
                    $tpost = get_post($pid);
                    if($tpost === null)
                    {
                        aiomatic_log_to_file('Failed to find post ID for embedding creation: ' . $pid);
                        continue;
                    }
                    $post_url = get_permalink($tpost->ID);
                    $post_title = $tpost->post_title;
                    $post_excerpt = $tpost->post_excerpt;
                    $post_id = $tpost->ID;
                    $post_content = $tpost->post_content;
                    if (strstr($aiomatic_Main_Settings['bulk_embedding_template'], '%%post_content%%') !== false && isset($aiomatic_Main_Settings['rewrite_embedding']) && trim($aiomatic_Main_Settings['rewrite_embedding']) == 'on' && isset($aiomatic_Main_Settings['embedding_rw_prompt']) && trim($aiomatic_Main_Settings['embedding_rw_prompt']) != '')
                    {
                        $embedding_rw_prompt = trim($aiomatic_Main_Settings['embedding_rw_prompt']);
                        $embedding_rw_prompt = str_replace('%%post_url%%', $post_url, $embedding_rw_prompt);
                        $embedding_rw_prompt = str_replace('%%post_title%%', $post_title, $embedding_rw_prompt);
                        $embedding_rw_prompt = str_replace('%%post_excerpt%%', $post_excerpt, $embedding_rw_prompt);
                        $embedding_rw_prompt = str_replace('%%post_content%%', strip_shortcodes($post_content), $embedding_rw_prompt);
                        $embedding_rw_prompt = str_replace('%%post_id%%', $post_id, $embedding_rw_prompt);
                        if($embedding_rw_prompt != '')
                        {
                            if(isset($aiomatic_Main_Settings['embedding_rw_model']) && trim($aiomatic_Main_Settings['embedding_rw_model']) != '')
                            {
                                $rw_model = trim($aiomatic_Main_Settings['embedding_rw_model']);
                            }
                            else
                            {
                                $rw_model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
                            }
                            if(isset($aiomatic_Main_Settings['emb_assistant_id']) && trim($aiomatic_Main_Settings['emb_assistant_id']) != '')
                            {
                                $emb_assistant_id = trim($aiomatic_Main_Settings['emb_assistant_id']);
                            }
                            else
                            {
                                $emb_assistant_id = '';
                            }
                            $all_models = aiomatic_get_all_models(true);
                            if(!in_array($rw_model, $all_models))
                            {
                                $rw_model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
                            }
                            $query_token_count = count(aiomatic_encode($embedding_rw_prompt));
                            $max_tokens = aiomatic_get_max_tokens($rw_model);
                            $available_tokens = aiomatic_compute_available_tokens($rw_model, $max_tokens, $embedding_rw_prompt, $query_token_count);
                            if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
                            {
                                $string_len = strlen($embedding_rw_prompt);
                                $string_len = $string_len / 2;
                                $string_len = intval(0 - $string_len);
                                $embedding_rw_prompt = aiomatic_substr($embedding_rw_prompt, 0, $string_len);
                                $embedding_rw_prompt = trim($embedding_rw_prompt);
                                $query_token_count = count(aiomatic_encode($embedding_rw_prompt));
                                $available_tokens = $max_tokens - $query_token_count;
                            }
                            if(!empty($embedding_rw_prompt))
                            {
                                $thread_id = '';
                                $aierror = '';
                                $finish_reason = '';
                                $generated_text = aiomatic_generate_text($token, $rw_model, $embedding_rw_prompt, $available_tokens, 1, 1, 0, 0, false, 'embeddingsOptimizer', 0, $finish_reason, $aierror, false, false, false, '', '', 'user', $emb_assistant_id, $thread_id, '', 'disabled', '', false, false);
                                if($generated_text === false)
                                {
                                    aiomatic_log_to_file('Failed to optimize post content for embeddings: ' . print_r($embedding_rw_prompt, true));
                                }
                                else
                                {
                                    $post_content = aiomatic_sanitize_ai_result($generated_text);
                                }
                            }
                        }
                    }
                    $emb_template = trim($aiomatic_Main_Settings['bulk_embedding_template']);
                    $emb_template = str_replace('%%post_url%%', $post_url, $emb_template);
                    $emb_template = str_replace('%%post_title%%', $post_title, $emb_template);
                    $emb_template = str_replace('%%post_excerpt%%', $post_excerpt, $emb_template);
                    $emb_template = str_replace('%%post_content%%', strip_shortcodes($post_content), $emb_template);
                    $emb_template = str_replace('%%post_id%%', $post_id, $emb_template);
                    $emb_template = aiomatic_replaceEmbeddingsAIPostShortcodes($emb_template, $post_id);
                    $current_user = wp_get_current_user();
                    if ( !($current_user instanceof WP_User) || !is_user_logged_in()) 
                    {
                        $emb_template = str_replace('%%user_name%%', '', $emb_template);
                        $emb_template = str_replace('%%user_email%%', '' , $emb_template);
                        $emb_template = str_replace('%%user_display_name%%', '', $emb_template);
                        $emb_template = str_replace('%%user_role%%', '', $emb_template);
                        $emb_template = str_replace('%%user_id%%', '' , $emb_template);
                        $emb_template = str_replace('%%user_firstname%%', '' , $emb_template);
                        $emb_template = str_replace('%%user_lastname%%', '' , $emb_template);
                        $emb_template = str_replace('%%user_description%%', '' , $emb_template);
                        $emb_template = str_replace('%%user_url%%', '' , $emb_template);
                    }
                    else
                    {
                        $emb_template = str_replace('%%user_name%%', $current_user->user_login, $emb_template);
                        $emb_template = str_replace('%%user_email%%', $current_user->user_email , $emb_template);
                        $emb_template = str_replace('%%user_display_name%%', $current_user->display_name, $emb_template);
                        $emb_template = str_replace('%%user_role%%', implode(',', $current_user->roles), $emb_template);
                        $emb_template = str_replace('%%user_id%%', $current_user->ID , $emb_template);
                        $emb_template = str_replace('%%user_firstname%%', $current_user->user_firstname , $emb_template);
                        $emb_template = str_replace('%%user_lastname%%', $current_user->user_lastname , $emb_template);
                        $user_desc = get_the_author_meta( 'description', $current_user->ID );
                        $emb_template = str_replace('%%user_description%%', $user_desc , $emb_template);
                        $user_url = get_the_author_meta( 'user_url', $current_user->ID );
                        $emb_template = str_replace('%%user_url%%', $user_url , $emb_template);
                    }
                    $emb_template = apply_filters('aiomatic_modify_ai_embeddings', $emb_template);
                    if($emb_template != '')
                    {
                        $namespace = '';
                        if (isset($aiomatic_Main_Settings['bulk_namspace']) && trim($aiomatic_Main_Settings['bulk_namspace']) != '')
                        {
                            $namespace = $aiomatic_Main_Settings['bulk_namspace'];
                        }
                        $rez = $embdedding->aiomatic_create_single_embedding_nojson($emb_template, $namespace);
                        if($rez['status'] == 'error')
                        {
                            aiomatic_log_to_file('Failed to save embedding for post id: ' . $post_id . ' error: ' . print_r($rez, true));
                        }
                    }
                }
                else
                {
                    wp_die('No embedding template set in plugin settings!');
                }
            }
        }
        else
        {
            wp_die('You need to set up an OpenAI API key in the Aiomatic plugin\' settings, for this to work!');
        }
        return admin_url( 'admin.php?page=aiomatic_embeddings_panel#tab-2' );
    }
    elseif ($action === 'aiomatic_processing') 
    {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        $current = 1;
        foreach($post_ids as $pid)
        {
            $tpost = get_post($pid);
            if($tpost === null)
            {
                aiomatic_log_to_file('Failed to find post ID for AI Content Editor processing: ' . $pid);
                continue;
            }
            if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) 
            {
                aiomatic_log_to_file('Manually processing post ' . $current . '/' . count($post_ids) . ', ID: ' . $pid);
            }
            aiomatic_do_post($tpost, true, 'skip', false);
            $current++;
        }
    }
    elseif ($action === 'aiomatic_edited') 
    {
        $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
        foreach($post_ids as $pid)
        {
            if (isset($aiomatic_Spinner_Settings['custom_name']) && trim($aiomatic_Spinner_Settings['custom_name']) != '')
            {
                $custom_name = trim($aiomatic_Spinner_Settings['custom_name']);
                $custom_name = str_replace('%%current_date%%', date("Y-m-d"), $custom_name);
            }
            else
            {
                $custom_name = 'aiomatic_published';
            }
            update_post_meta($pid, $custom_name, 'pub');
        }
    }
    elseif ($action === 'aiomatic_not_edited') 
    {
        $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
        foreach($post_ids as $pid)
        {
            if (isset($aiomatic_Spinner_Settings['custom_name']) && trim($aiomatic_Spinner_Settings['custom_name']) != '')
            {
                $custom_name = trim($aiomatic_Spinner_Settings['custom_name']);
                $custom_name = str_replace('%%current_date%%', date("Y-m-d"), $custom_name);
            }
            else
            {
                $custom_name = 'aiomatic_published';
            }
            delete_post_meta($pid, $custom_name);
        }
    }
    return $redirect_to;
}
function aiomatic_custom_admin_favicon() 
{
    $screen = get_current_screen();
    if ($screen && isset($screen->id) && (aiomatic_starts_with($screen->id, 'aiomatic_page_') !== false || aiomatic_starts_with($screen->id, 'toplevel_page_aiomatic_') !== false || $screen->id == 'media_page_aiomatic-automatic-ai-content-writer'))
    {
        $favicon_url = plugins_url('/images/icon.png', __FILE__);
        echo '<link rel="icon" href="' . esc_url($favicon_url) . '" />';
    }
}
add_action('admin_head', 'aiomatic_custom_admin_favicon');
add_action('transition_post_status', 'aiomatic_embeddings_new_post', 10, 3);
function aiomatic_embeddings_new_post($new_status, $old_status, $post)
{
    if ('publish' !== $new_status or 'publish' === $old_status)
    {
        return;
    }
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] === 'on') 
    {
        if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
        {
            if(isset($aiomatic_Main_Settings['index_types']) && is_array($aiomatic_Main_Settings['index_types']))
            {
                if(in_array($post->post_type, $aiomatic_Main_Settings['index_types']))
                {
                    require_once(dirname(__FILE__) . "/res/Embeddings.php");
                    $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
                    $appids = array_filter($appids);
                    $token = $appids[array_rand($appids)];
                    $token = apply_filters('aiomatic_openai_api_key', $token);
                    $embdedding = new Aiomatic_Embeddings($token);
                    if (isset($aiomatic_Main_Settings['embedding_template']) && trim($aiomatic_Main_Settings['embedding_template']) != '')
                    {
                        $post_url = get_permalink($post->ID);
                        $post_title = $post->post_title;
                        $post_excerpt = $post->post_excerpt;
                        $post_id = $post->ID;
                        $post_content = $post->post_content;
                        if (strstr($aiomatic_Main_Settings['embedding_template'], '%%post_content%%') !== false && isset($aiomatic_Main_Settings['rewrite_embedding']) && trim($aiomatic_Main_Settings['rewrite_embedding']) == 'on' && isset($aiomatic_Main_Settings['embedding_rw_prompt']) && trim($aiomatic_Main_Settings['embedding_rw_prompt']) != '')
                        {
                            $embedding_rw_prompt = trim($aiomatic_Main_Settings['embedding_rw_prompt']);
                            $embedding_rw_prompt = str_replace('%%post_url%%', $post_url, $embedding_rw_prompt);
                            $embedding_rw_prompt = str_replace('%%post_title%%', $post_title, $embedding_rw_prompt);
                            $embedding_rw_prompt = str_replace('%%post_excerpt%%', $post_excerpt, $embedding_rw_prompt);
                            $embedding_rw_prompt = str_replace('%%post_content%%', strip_shortcodes($post_content), $embedding_rw_prompt);
                            $embedding_rw_prompt = str_replace('%%post_id%%', $post_id, $embedding_rw_prompt);
                            if($embedding_rw_prompt != '')
                            {
                                if(isset($aiomatic_Main_Settings['embedding_rw_model']) && trim($aiomatic_Main_Settings['embedding_rw_model']) != '')
                                {
                                    $rw_model = trim($aiomatic_Main_Settings['embedding_rw_model']);
                                }
                                else
                                {
                                    $rw_model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
                                }
                                if(isset($aiomatic_Main_Settings['emb_assistant_id']) && trim($aiomatic_Main_Settings['emb_assistant_id']) != '')
                                {
                                    $emb_assistant_id = trim($aiomatic_Main_Settings['emb_assistant_id']);
                                }
                                else
                                {
                                    $emb_assistant_id = '';
                                }
                                $all_models = aiomatic_get_all_models(true);
                                if(!in_array($rw_model, $all_models))
                                {
                                    $rw_model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
                                }
                                $query_token_count = count(aiomatic_encode($embedding_rw_prompt));
                                $max_tokens = aiomatic_get_max_tokens($rw_model);
                                $available_tokens = aiomatic_compute_available_tokens($rw_model, $max_tokens, $embedding_rw_prompt, $query_token_count);
                                if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
                                {
                                    $string_len = strlen($embedding_rw_prompt);
                                    $string_len = $string_len / 2;
                                    $string_len = intval(0 - $string_len);
                                    $embedding_rw_prompt = aiomatic_substr($embedding_rw_prompt, 0, $string_len);
                                    $embedding_rw_prompt = trim($embedding_rw_prompt);
                                    $query_token_count = count(aiomatic_encode($embedding_rw_prompt));
                                    $available_tokens = $max_tokens - $query_token_count;
                                }
                                if(!empty($embedding_rw_prompt))
                                {
                                    $thread_id = '';
                                    $aierror = '';
                                    $finish_reason = '';
                                    $generated_text = aiomatic_generate_text($token, $rw_model, $embedding_rw_prompt, $available_tokens, 1, 1, 0, 0, false, 'embeddingsOptimizer', 0, $finish_reason, $aierror, false, false, false, '', '', 'user', $emb_assistant_id, $thread_id, '', 'disabled', '', false, false);
                                    if($generated_text === false)
                                    {
                                        aiomatic_log_to_file('Failed to optimize post content for auto embeddings: ' . print_r($embedding_rw_prompt, true));
                                    }
                                    else
                                    {
                                        $post_content = aiomatic_sanitize_ai_result($generated_text);
                                    }
                                }
                            }
                        }
                        $emb_template = trim($aiomatic_Main_Settings['embedding_template']);
                        $emb_template = str_replace('%%post_url%%', $post_url, $emb_template);
                        $emb_template = str_replace('%%post_title%%', $post_title, $emb_template);
                        $emb_template = str_replace('%%post_excerpt%%', $post_excerpt, $emb_template);
                        $emb_template = str_replace('%%post_content%%', strip_shortcodes($post_content), $emb_template);
                        $emb_template = str_replace('%%post_id%%', $post_id, $emb_template);
                        $emb_template = aiomatic_replaceEmbeddingsAIPostShortcodes($emb_template, $post_id);
                        $current_user = wp_get_current_user();
                        if ( !($current_user instanceof WP_User) || !is_user_logged_in()) 
                        {
                            $emb_template = str_replace('%%user_name%%', '', $emb_template);
                            $emb_template = str_replace('%%user_email%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_display_name%%', '', $emb_template);
                            $emb_template = str_replace('%%user_role%%', '', $emb_template);
                            $emb_template = str_replace('%%user_id%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_firstname%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_lastname%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_description%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_url%%', '' , $emb_template);
                        }
                        else
                        {
                            $emb_template = str_replace('%%user_name%%', $current_user->user_login, $emb_template);
                            $emb_template = str_replace('%%user_email%%', $current_user->user_email , $emb_template);
                            $emb_template = str_replace('%%user_display_name%%', $current_user->display_name, $emb_template);
                            $emb_template = str_replace('%%user_role%%', implode(',', $current_user->roles), $emb_template);
                            $emb_template = str_replace('%%user_id%%', $current_user->ID , $emb_template);
                            $emb_template = str_replace('%%user_firstname%%', $current_user->user_firstname , $emb_template);
                            $emb_template = str_replace('%%user_lastname%%', $current_user->user_lastname , $emb_template);
                            $user_desc = get_the_author_meta( 'description', $current_user->ID );
                            $emb_template = str_replace('%%user_description%%', $user_desc , $emb_template);
                            $user_url = get_the_author_meta( 'user_url', $current_user->ID );
                            $emb_template = str_replace('%%user_url%%', $user_url , $emb_template);
                        }
                        $emb_template = apply_filters('aiomatic_modify_ai_embeddings', $emb_template);
                        if($emb_template != '')
                        {
                            $namespace = '';
                            if (isset($aiomatic_Main_Settings['auto_namspace']) && trim($aiomatic_Main_Settings['auto_namspace']) != '')
                            {
                                $namespace = $aiomatic_Main_Settings['auto_namspace'];
                            }
                            $rez = $embdedding->aiomatic_create_single_embedding_nojson($emb_template, $namespace);
                            if($rez['status'] == 'error')
                            {
                                aiomatic_log_to_file('Failed to automatically save embedding for post id: ' . $post_id . ' error: ' . print_r($rez, true));
                            }
                        }
                    }
                    else
                    {
                        aiomatic_log_to_file('No auto embedding template set in plugin settings!');
                        return;
                    }
                }
            }
        }
    }
}

add_action('comment_post', 'aiomatic_embeddings_new_comment', 10, 3);
function aiomatic_embeddings_new_comment($comment_ID, $comment_approved, $commentdata)
{
    if ($comment_approved != 1) 
    {
        return;
    }

    $post_id = $commentdata['comment_post_ID'];
    $post = get_post($post_id);
    
    if (!$post) 
    {
        return;
    }

    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] === 'on') 
    {
        if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
        {
            if (isset($aiomatic_Main_Settings['comment_index_types']) && is_array($aiomatic_Main_Settings['comment_index_types'])) 
            {
                if (in_array($post->post_type, $aiomatic_Main_Settings['comment_index_types'])) 
                {
                    require_once(dirname(__FILE__) . "/res/Embeddings.php");
                    $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
                    $appids = array_filter($appids);
                    $token = $appids[array_rand($appids)];
                    $token = apply_filters('aiomatic_openai_api_key', $token);
                    $embedding = new Aiomatic_Embeddings($token);

                    $comment_content = $commentdata['comment_content'];
                    $comment_author = $commentdata['comment_author'];
                    $post_url = get_permalink($post_id);
                    $post_title = $post->post_title;
                    $post_content = $post->post_content;
                    $post_excerpt = $post->post_excerpt;

                    if (isset($aiomatic_Main_Settings['comment_embedding_template']) && trim($aiomatic_Main_Settings['comment_embedding_template']) != '') 
                    {
                        $emb_template = trim($aiomatic_Main_Settings['comment_embedding_template']);
                        $emb_template = str_replace('%%post_url%%', $post_url, $emb_template);
                        $emb_template = str_replace('%%post_title%%', $post_title, $emb_template);
                        $emb_template = str_replace('%%post_content%%', $post_content, $emb_template);
                        $emb_template = str_replace('%%post_excerpt%%', $post_excerpt, $emb_template);
                        $emb_template = str_replace('%%post_id%%', $post_id, $emb_template);
                        $emb_template = str_replace('%%comment_content%%', strip_shortcodes($comment_content), $emb_template);
                        $emb_template = str_replace('%%comment_id%%', $comment_ID, $emb_template);
                        $emb_template = str_replace('%%comment_author%%', $comment_author, $emb_template);
                        $emb_template = aiomatic_replaceEmbeddingsAIPostShortcodes($emb_template, $post_id);
                        $current_user = wp_get_current_user();
                        if ( !($current_user instanceof WP_User) || !is_user_logged_in()) 
                        {
                            $emb_template = str_replace('%%user_name%%', '', $emb_template);
                            $emb_template = str_replace('%%user_email%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_display_name%%', '', $emb_template);
                            $emb_template = str_replace('%%user_role%%', '', $emb_template);
                            $emb_template = str_replace('%%user_id%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_firstname%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_lastname%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_description%%', '' , $emb_template);
                            $emb_template = str_replace('%%user_url%%', '' , $emb_template);
                        }
                        else
                        {
                            $emb_template = str_replace('%%user_name%%', $current_user->user_login, $emb_template);
                            $emb_template = str_replace('%%user_email%%', $current_user->user_email , $emb_template);
                            $emb_template = str_replace('%%user_display_name%%', $current_user->display_name, $emb_template);
                            $emb_template = str_replace('%%user_role%%', implode(',', $current_user->roles), $emb_template);
                            $emb_template = str_replace('%%user_id%%', $current_user->ID , $emb_template);
                            $emb_template = str_replace('%%user_firstname%%', $current_user->user_firstname , $emb_template);
                            $emb_template = str_replace('%%user_lastname%%', $current_user->user_lastname , $emb_template);
                            $user_desc = get_the_author_meta( 'description', $current_user->ID );
                            $emb_template = str_replace('%%user_description%%', $user_desc , $emb_template);
                            $user_url = get_the_author_meta( 'user_url', $current_user->ID );
                            $emb_template = str_replace('%%user_url%%', $user_url , $emb_template);
                        }
                        $emb_template = apply_filters('aiomatic_modify_ai_embeddings', $emb_template);

                        if ($emb_template != '') 
                        {
                            $namespace = '';
                            if (isset($aiomatic_Main_Settings['comment_auto_namspace']) && trim($aiomatic_Main_Settings['comment_auto_namspace']) != '') 
                            {
                                $namespace = $aiomatic_Main_Settings['comment_auto_namspace'];
                            }
                            $rez = $embedding->aiomatic_create_single_embedding_nojson($emb_template, $namespace);
                            if ($rez['status'] == 'error') 
                            {
                                aiomatic_log_to_file('Failed to automatically save embedding for comment id: ' . $comment_ID . ' error: ' . print_r($rez, true));
                            }
                        } 
                        else 
                        {
                            aiomatic_log_to_file('No auto embedding template set in plugin settings!');
                            return;
                        }
                    }
                }
            }
        }
    }
}

function aiomatic_auto_write_tax_description($tag_ID, $taxonomy) 
{
    $my_term = get_term_by('id', $tag_ID, $taxonomy);
    if($my_term == false)
	{
		aiomatic_log_to_file('Taxonomy ID not found: ' . print_r($tag_ID, true));
        return false;
	}
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (!isset($aiomatic_Main_Settings['app_id']) || trim($aiomatic_Main_Settings['app_id']) == '') {
        aiomatic_log_to_file('You need to enter an OpenAI API key in plugin settings!');
        return false;
	}
    
    if (isset($aiomatic_Main_Settings['tax_description_prompt']) && trim($aiomatic_Main_Settings['tax_description_prompt']) != '') 
    {
        $prompt = trim($aiomatic_Main_Settings['tax_description_prompt']);
    }
    else
    {
        $prompt = 'Write a description for a WordPress %%term_taxonomy_name%% with the following title: "%%term_name%%"';
    }
    if (isset($aiomatic_Main_Settings['tax_description_model']) && trim($aiomatic_Main_Settings['tax_description_model']) != '') 
    {
        $model = trim($aiomatic_Main_Settings['tax_description_model']);
    }
    else
    {
        $model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
    }
    if (isset($aiomatic_Main_Settings['tax_assistant_id']) && trim($aiomatic_Main_Settings['tax_assistant_id']) != '') 
    {
        $tax_assistant_id = trim($aiomatic_Main_Settings['tax_assistant_id']);
    }
    else
    {
        $tax_assistant_id = '';
    }
	$all_models = aiomatic_get_all_models(true);
	if(!in_array($model, $all_models))
    {
        $model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
    }
	$new_post_content = '';
	$appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
	$appids = array_filter($appids);
	$token = $appids[array_rand($appids)];
    $token = apply_filters('aiomatic_openai_api_key', $token);
    $prompt = str_replace('%%term_id%%', $my_term->term_id, $prompt);
    $prompt = str_replace('%%term_name%%', $my_term->name, $prompt);
    $prompt = str_replace('%%term_slug%%', $my_term->slug, $prompt);
    $prompt = str_replace('%%term_description%%', $my_term->description, $prompt);
    $prompt = str_replace('%%term_taxonomy_name%%', $my_term->taxonomy, $prompt);
    $prompt = str_replace('%%term_taxonomy_id%%', $my_term->term_taxonomy_id, $prompt);
	$query_token_count = count(aiomatic_encode($prompt));
    $max_tokens = aiomatic_get_max_tokens($model);
	$available_tokens = aiomatic_compute_available_tokens($model, $max_tokens, $prompt, $query_token_count);
	if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
	{
		$string_len = strlen($prompt);
		$string_len = $string_len / 2;
		$string_len = intval(0 - $string_len);
        $prompt = aiomatic_substr($prompt, 0, $string_len);
		$prompt = trim($prompt);
		if(empty($prompt))
		{
            aiomatic_log_to_file('Incorrect taxonomy writer prompt provided: ' . print_r($prompt, true));
            return false;
		}
		$query_token_count = count(aiomatic_encode($prompt));
		$available_tokens = $max_tokens - $query_token_count;
	}
    $thread_id = '';
	$aierror = '';
	$finish_reason = '';
	$generated_text = aiomatic_generate_text($token, $model, $prompt, $available_tokens, 1, 1, 0, 0, false, 'taxonomyAutoDescriptionWriter', 0, $finish_reason, $aierror, false, false, false, '', '', 'user', $tax_assistant_id, $thread_id, '', 'disabled', '', false, false);
	if($generated_text === false)
	{
		aiomatic_log_to_file('Failed to automatically generated taxonomy description: ' . print_r($prompt, true));
        return false;
	}
	else
	{
		$new_post_content = aiomatic_sanitize_ai_result($generated_text);
	}
    return $new_post_content;
}

function aiomatic_auto_write_tax_SEO_description($tag_ID, $taxonomy) 
{
    $my_term = get_term_by('id', $tag_ID, $taxonomy);
    if($my_term == false)
	{
		aiomatic_log_to_file('Taxonomy ID not found: ' . print_r($tag_ID, true));
        return false;
	}
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (!isset($aiomatic_Main_Settings['app_id']) || trim($aiomatic_Main_Settings['app_id']) == '') {
        aiomatic_log_to_file('You need to enter an OpenAI API key in plugin settings!');
        return false;
	}
    
    if (isset($aiomatic_Main_Settings['tax_seo_description_prompt']) && trim($aiomatic_Main_Settings['tax_seo_description_prompt']) != '') 
    {
        $prompt = trim($aiomatic_Main_Settings['tax_seo_description_prompt']);
    }
    else
    {
        $prompt = 'Write a SEO friendly short description (maximum 50 words) for a WordPress %%term_taxonomy_name%% with the following title: "%%term_name%%"';
    }
    if (isset($aiomatic_Main_Settings['tax_seo_description_model']) && trim($aiomatic_Main_Settings['tax_seo_description_model']) != '') 
    {
        $model = trim($aiomatic_Main_Settings['tax_seo_description_model']);
    }
    else
    {
        $model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
    }
    if (isset($aiomatic_Main_Settings['tax_seo_assistant_id']) && trim($aiomatic_Main_Settings['tax_seo_assistant_id']) != '') 
    {
        $tax_seo_assistant_id = trim($aiomatic_Main_Settings['tax_seo_assistant_id']);
    }
    else
    {
        $tax_seo_assistant_id = '';
    }
	$all_models = aiomatic_get_all_models(true);
	if(!in_array($model, $all_models))
    {
        $model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
    }
	$new_post_content = '';
	$appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
	$appids = array_filter($appids);
	$token = $appids[array_rand($appids)];
    $token = apply_filters('aiomatic_openai_api_key', $token);
    $prompt = str_replace('%%term_id%%', $my_term->term_id, $prompt);
    $prompt = str_replace('%%term_name%%', $my_term->name, $prompt);
    $prompt = str_replace('%%term_slug%%', $my_term->slug, $prompt);
    $prompt = str_replace('%%term_description%%', $my_term->description, $prompt);
    $prompt = str_replace('%%term_taxonomy_name%%', $my_term->taxonomy, $prompt);
    $prompt = str_replace('%%term_taxonomy_id%%', $my_term->term_taxonomy_id, $prompt);
	$query_token_count = count(aiomatic_encode($prompt));
    $max_tokens = aiomatic_get_max_tokens($model);
	$available_tokens = aiomatic_compute_available_tokens($model, $max_tokens, $prompt, $query_token_count);
	if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
	{
		$string_len = strlen($prompt);
		$string_len = $string_len / 2;
		$string_len = intval(0 - $string_len);
        $prompt = aiomatic_substr($prompt, 0, $string_len);
		$prompt = trim($prompt);
		if(empty($prompt))
		{
            aiomatic_log_to_file('Incorrect taxonomy writer prompt provided: ' . print_r($prompt, true));
            return false;
		}
		$query_token_count = count(aiomatic_encode($prompt));
		$available_tokens = $max_tokens - $query_token_count;
	}
    $thread_id = '';
	$aierror = '';
	$finish_reason = '';
	$generated_text = aiomatic_generate_text($token, $model, $prompt, $available_tokens, 1, 1, 0, 0, false, 'taxonomyAutoSEODescriptionWriter', 0, $finish_reason, $aierror, false, false, false, '', '', 'user', $tax_seo_assistant_id, $thread_id, '', 'disabled', '', false, false);
	if($generated_text === false)
	{
		aiomatic_log_to_file('Failed to automatically generated taxonomy description: ' . print_r($prompt, true));
        return false;
	}
	else
	{
		$new_post_content = aiomatic_sanitize_ai_result($generated_text);
	}
    return $new_post_content;
}
function aiomatic_generate_taxonomy_description($term_id, $tt_id, $taxonomy) 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] === 'on') 
    {
        if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
        {
            if (isset($aiomatic_Main_Settings['tax_description_auto']) && is_array($aiomatic_Main_Settings['tax_description_auto']) && in_array($taxonomy, $aiomatic_Main_Settings['tax_description_auto']))
            {
                $term = get_term($term_id, $taxonomy);
                if (is_wp_error($term) || $term === null || $term === false) 
                {
                    return;
                }
                if (!empty($term->description)) {
                    return;
                }
                $description = aiomatic_auto_write_tax_description($term_id, $taxonomy);
                if(!empty($description))
                {
                    remove_filter( 'pre_term_description', 'wp_filter_kses' );
                    remove_filter( 'term_description', 'wp_kses_data' );
                    wp_update_term($term_id, $taxonomy, array(
                        'description' => $description
                    ));
                    add_filter( 'pre_term_description', 'wp_filter_kses' );
                    add_filter( 'term_description', 'wp_kses_data' );
                    if (isset($aiomatic_Main_Settings['tax_seo_auto']))
                    {
                        if($aiomatic_Main_Settings['tax_seo_auto'] == 'copy')
                        {
                            aiomatic_save_term_seo_description($term_id, $description, $taxonomy);
                        }
                        elseif($aiomatic_Main_Settings['tax_seo_auto'] == 'write')
                        {
                            $description = aiomatic_auto_write_tax_SEO_description($term_id, $taxonomy);
                            if(!empty($description))
                            {
                                aiomatic_save_term_seo_description($term_id, $description, $taxonomy);
                            }
                        }
                    }
                }
            }
        }
    }
}
add_action('created_term', 'aiomatic_generate_taxonomy_description', 10, 3);

function aiomatic_add_custom_button_to_taxonomy_edit_menu() {
    global $pagenow;
    if ($pagenow === 'term.php' && isset($_GET['taxonomy']) && isset($_GET['tag_ID'])) {
        $taxonomy = $_GET['taxonomy'];
        $tag_ID = $_GET['tag_ID'];
        $name = md5(get_bloginfo());
        wp_enqueue_script($name . '-tax-script', plugins_url('scripts/taxonomy.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
        wp_localize_script( $name . '-tax-script', 'AICustomButtonData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'taxonomy' => $taxonomy,
            'tagID' => $tag_ID,
            'nonce' => wp_create_nonce('openai-ajax-nonce'),
            'writeMessage' => esc_html__('Write Description Using AI', 'aiomatic-automatic-ai-content-writer'),
            'moreSettings' => esc_html__('INFO: Configure AI Writer Prompts in the "Taxonomy Description Writer" tab from', 'aiomatic-automatic-ai-content-writer') . '&nbsp;<a href="' . admin_url('admin.php?page=aiomatic_admin_settings') . '" target="_blank">' . esc_html__('here', 'aiomatic-automatic-ai-content-writer') . '</a>',
        ) );
    }
}
add_action( 'admin_footer', 'aiomatic_add_custom_button_to_taxonomy_edit_menu' );

add_action('wp_head', 'aiomatic_wp_head_seo',1);
function aiomatic_wp_head_seo()
{
    if(is_singular())
    {
        $aiomatic_meta_description = get_post_meta(get_the_ID(), 'aiomatic_html_meta', true);
        $aiomatic_seo_option = false;
        $seo_plugin_activated = aiomatic_seo_plugins_active();
        if($seo_plugin_activated !== false) 
        {
            $aiomatic_seo_option = get_option($seo_plugin_activated, false);
        }
        if(!empty($aiomatic_meta_description) && !$aiomatic_seo_option)
        {
            ?>
            <meta name="description" content="<?php echo esc_html($aiomatic_meta_description)?>">
            <meta name="og:description" content="<?php echo esc_html($aiomatic_meta_description)?>">
            <?php
        }
    }
}
add_filter('comment_row_actions','aiomatic_comment_action', 10, 2);
add_action('admin_footer','aiomatic_comment_scripts');
function aiomatic_comment_action($actions, $post)
{
    if(current_user_can('access_aiomatic_menu')) {
        $actions['aiomatic_commenter'] = sprintf('<a id="aiomatic_comment_replier" class="aiomatic_comment_replier" href="javascript:void(0)" data-id="%s">%s</a>',
        esc_attr($post->comment_ID),
        esc_html__('AI Generated Reply', 'aiomatic-automatic-ai-content-writer'));
    }
    return $actions;
}
function aiomatic_comment_scripts()
{
    if(current_user_can('access_aiomatic_menu')) {
        $name = md5(get_bloginfo());
        wp_enqueue_script($name . '-commenter-script', plugins_url('scripts/ai-commenter.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
        $footer_conf_settings = array(
            'nonce' => wp_create_nonce('openai-comment-nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'aireplytext' => esc_html__('AI Generated Reply', 'aiomatic-automatic-ai-content-writer'),
            'processingtext' => esc_html__('This process was already started!', 'aiomatic-automatic-ai-content-writer'),
            'cannotfind' => esc_html__('Cannot find this comment ID!', 'aiomatic-automatic-ai-content-writer'),
            'working' => esc_html__('Working...', 'aiomatic-automatic-ai-content-writer')
        );
        wp_localize_script($name . '-commenter-script', 'mycommentssettings', $footer_conf_settings);
    }
}
function aiomatic_get_max_tokens($model)
{
    $model_tokens = array(
        'gpt-4-1106-preview' => 4000, //4096 for output, 128000 for input
        'gpt-4-0125-preview' => 4000, //4096 for output, 128000 for input
        'gpt-4-turbo-preview' => 4000, //4096 for output, 128000 for input
        'gpt-4-turbo' => 4000, //4096 for output, 128000 for input
        'gpt-4-turbo-2024-04-09' => 4000, //4096 for output, 128000 for input
        'gpt-4o' => 4000, //4096 for output, 128000 for input
        'gpt-4o-2024-05-13' => 4000, //4096 for output, 128000 for input
        'chatgpt-4o-latest' => 16000, //16384 for output, 128000 for input 
        'o1-preview' => 32000, //32768 for output, 128000 for input , 
        'o1-preview-2024-09-12' => 32000, //32768 for output, 128000 for input , 
        'o1-mini' => 65000, //65536 for output, 128000 for input , 
        'o1-mini-2024-09-12' => 65000, //65536 for output, 128000 for input ,
        'gpt-4o-2024-08-06' => 16000, //16384 for output, 128000 for input
        'gpt-4o-mini' => 16000, //16384 for output, 128000 for input
        'gpt-4o-mini-2024-07-18' => 16000, //16384 for output, 128000 for input
        'gpt-3.5-turbo-0125' => 4000, //4096,
        'gpt-4-vision-preview' => 4000, //4096 for output, 128000 for input
        'gpt-4' => 8100, //8192,
        'gpt-4-32k' => 32600, //32768,
        'gpt-4-0613' => 8100, //8192,
        'gpt-4-32k-0613' => 32600, //32768,
        'gpt-4-0314' => 8100, //8192,
        'gpt-4-32k-0314' => 32600, //32768,
        'gpt-3.5-turbo-1106' => 4000, //4096,
        'gpt-4o-mini' => 4000, //4096,
        'gpt-3.5-turbo-16k' => 16200, //16385,
        'gpt-3.5-turbo-instruct' => 4000, //4096,
        'text-davinci-003' => 4000, //4096,
        'text-davinci-002' => 4000, //4096,
        'code-davinci-002' => 8000, //8001,
        'text-moderation-latest' => 32600, //32768,
        'omni-moderation-latest' => 32600, //32768,
        'text-moderation-stable' => 32600, //32768,
        'babbage-002' => 16200, //16385,
        'davinci-002' => 16200, //16385,
        'text-curie-001' => 2000, //2049,
        'text-babbage-001' => 2000, //2049,
        'text-ada-001' => 2000, //2049,
        'davinci' => 2000, //2049,
        'curie' => 2000, //2049,
        'babbage' => 2000, //2049,
        'ada' => 2000, //2049,
        'text-davinci-edit-001' => 4000, //4096,
        'code-davinci-edit-001' => 4000, //4096,
        'text-embedding-ada-002' => 8000, //8191,
        'text-embedding-3-small' => 8000, //8191,
        'text-embedding-3-large' => 8000, //8191,
        'claude-instant-1' => 4000, //4096,
        'claude-instant-1.2' => 4000, //4096,
        'claude-2.0' => 4000, //4096,
        'claude-2.1' => 4000, //4096,
        'claude-3-opus-20240229' => 4000, //4096,
        'claude-3-sonnet-20240229' => 4000, //4096,
        'claude-3-haiku-20240307' => 4000, //4096,
        'claude-3-5-sonnet-20240620' => 4000, //4096,
        'claude-3-5-sonnet-20241022' => 8000, //8192,
        'claude-3-5-haiku-20241022' => 8000, //8192,
        //google api limits https://cloud.google.com/vertex-ai/docs/generative-ai/learn/models#foundation_models
        'gemini-pro' => 8100, //8191,
        'gemini-1.5-pro-latest' => 8100, //8191,
        'gemini-1.0-pro' => 8100, //8191,
        'gemini-1.5-flash-latest' => 8100, //8191,
        'gemini-1.5-flash-8b-latest' => 8100, //8191,
        'chat-bison-001' => 1000, //1024,
        'text-bison-001' => 1000, //1024,
        'grok-beta' => 8000, //8191,
        'embedding-001' => 2048, //2048,
        'text-embedding-004' => 2048, //2048,
    );
    $model_tokens = array_merge($model_tokens, aiomatic_get_perplexity_model_tokens());
    $model_tokens = array_merge($model_tokens, aiomatic_get_groq_model_tokens());
    $model_tokens = array_merge($model_tokens, aiomatic_get_nvidia_model_tokens());
    if(isset($model_tokens[$model]))
    {
        return $model_tokens[$model];
    }
    return AIOMATIC_DEFAULT_MAX_TOKENS;
}
function aiomatic_get_max_input_tokens($model)
{
    $model_tokens = array(
        'gpt-4-1106-preview' => 128000, //4096 for output, 128000 for input
        'gpt-4-0125-preview' => 128000, //4096 for output, 128000 for input
        'gpt-4-turbo-preview' => 128000, //4096 for output, 128000 for input
        'gpt-4-turbo' => 128000, //4096 for output, 128000 for input
        'gpt-4-turbo-2024-04-09' => 128000, //4096 for output, 128000 for input
        'gpt-4o' => 128000, //4096 for output, 128000 for input
        'gpt-4o-2024-05-13' => 128000, //4096 for output, 128000 for input
        'chatgpt-4o-latest' => 128000, //4096 for output, 128000 for input 
        'o1-preview' => 128000, //32768 for output, 128000 for input 
        'o1-preview-2024-09-12' => 128000, //32768 for output, 128000 for input 
        'o1-mini' => 128000, //65536 for output, 128000 for input  
        'o1-mini-2024-09-12' => 128000, //65536 for output, 128000 for input  
        'gpt-4o-2024-08-06' => 128000, //16384 for output, 128000 for input
        'gpt-4o-mini' => 128000, //4096 for output, 128000 for input
        'gpt-4o-mini-2024-07-18' => 128000, //4096 for output, 128000 for input
        'gpt-3.5-turbo-0125' => 16385, //4096,
        'gpt-4-vision-preview' => 128000, //4096 for output, 128000 for input
        'gpt-4' => 8192, //8192,
        'gpt-4-32k' => 32768, //32768,
        'gpt-4-0613' => 8192, //8192,
        'gpt-4-32k-0613' => 32768, //32768,
        'gpt-4-0314' => 8192, //8192,
        'gpt-4-32k-0314' => 32768, //32768,
        'gpt-3.5-turbo-1106' => 16385, //4096, 16385 for input
        'gpt-4o-mini' => 4096, //4096,
        'gpt-3.5-turbo-16k' => 16385, //16385,
        'gpt-3.5-turbo-instruct' => 4096, //4096,
        'text-davinci-003' => 4096, //4096,
        'text-davinci-002' => 4096, //4096,
        'code-davinci-002' => 8001, //8001,
        'text-moderation-latest' => 32768, //32768,
        'omni-moderation-latest' => 32768, //32768,
        'text-moderation-stable' => 32768, //32768,
        'babbage-002' => 16385, //16385,
        'davinci-002' => 16385, //16385,
        'text-curie-001' => 2049, //2049,
        'text-babbage-001' => 2049, //2049,
        'text-ada-001' => 2049, //2049,
        'davinci' => 2049, //2049,
        'curie' => 2049, //2049,
        'babbage' => 2049, //2049,
        'ada' => 2049, //2049,
        'text-davinci-edit-001' => 4096, //4096,
        'code-davinci-edit-001' => 4096, //4096,
        'text-embedding-ada-002' => 8000, //8191,
        'text-embedding-3-small' => 8000, //8191,
        'text-embedding-3-large' => 8000, //8191,
        'claude-instant-1' => 99800, //100000,
        'claude-instant-1.2' => 99800, //100000,
        'claude-2.0' => 99800, //100000,
        'claude-2.1' => 199800, //200000,
        'claude-3-opus-20240229' => 199800, //200000,
        'claude-3-sonnet-20240229' => 199800, //200000,
        'claude-3-haiku-20240307' => 199800, //200000,
        'claude-3-5-sonnet-20240620' => 199800, //200000,
        'claude-3-5-sonnet-20241022' => 199800, //200000,
        'claude-3-5-haiku-20241022' => 199800, //200000,
        //google api limits https://cloud.google.com/vertex-ai/docs/generative-ai/learn/models#foundation_models
        'gemini-pro' => 31000, //32768,
        'gemini-1.0-pro' => 32000, //32760,
        'gemini-1.5-pro-latest' => 999000, //1000000,
        'gemini-1.5-flash-latest' => 999000, //1000000,
        'gemini-1.5-flash-8b-latest' => 999000, //1000000,
        'chat-bison-001' => 8192, //8192,
        'text-bison-001' => 8192, //8192,
        'grok-beta' => 128000, //128000,
        'embedding-001' => 2048, //2048,
        'text-embedding-004' => 2048, //2048,
    );
    $model_tokens = array_merge($model_tokens, aiomatic_get_perplexity_model_tokens());
    $model_tokens = array_merge($model_tokens, aiomatic_get_groq_model_tokens());
    $model_tokens = array_merge($model_tokens, aiomatic_get_nvidia_model_tokens());
    if(isset($model_tokens[$model]))
    {
        return $model_tokens[$model];
    }
    return AIOMATIC_DEFAULT_MAX_TOKENS;
}
function aiomatic_get_max_input_tokens_openrouter($model)
{
    static $openrouter_arr = false;
    if($openrouter_arr === false)
    {
        try
        {
            $openrouter_arr = aiomatic_get_openrouter_models();
            if($openrouter_arr !== false)
            {
                foreach($openrouter_arr['source_list'] as $smodel)
                {
                    if($model === $smodel['model'])
                    {
                        return $smodel['maxContextualTokens'];
                    }
                }
            }
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to list OpenRouter models: ' . $e->getMessage());
        }
    }
    return AIOMATIC_DEFAULT_MAX_TOKENS;
}
function aiomatic_get_perplexity_model_tokens()
{
    return array(
        'codellama-34b-instruct' => 16000,
        'codellama-70b-instruct' => 16000,
        'llama-2-70b-chat' => 4000,
        'mistral-7b-instruct' => 4000,
        'mixtral-8x7b-instruct' => 4000,
        'pplx-7b-chat' => 8100,
        'pplx-70b-chat' => 4000,
        'pplx-7b-online' => 4000,
        'pplx-70b-online' => 4000,
    );
}
function aiomatic_get_groq_model_tokens()
{
    return array(
        'llama-3.1-405b-reasoning' => 8000,
        'llama-3.1-70b-versatile' => 8000,
        'llama-3.1-8b-instant' => 8000,
        'llama3-groq-70b-8192-tool-use-preview' => 8000,
        'llama3-groq-8b-8192-tool-use-preview' => 8000,
        'llama3-70b-8192' => 8000,
        'llama3-8b-8192' => 8000,
        'mixtral-8x7b-32768' => 8000,
        'gemma-7b-it' => 8000,
        'gemma2-9b-it' => 8000
    );
}function aiomatic_get_nvidia_model_tokens()
{
    return array(
        'nvidia/llama-3.1-nemotron-70b-instruct' => 4000,
        'nvidia/nemotron-4-mini-hindi-4b-instruct' => 2000,  
        'ibm/granite-guardian-3.0-8b' => 2000,  
        'ibm/granite-3.0-8b-instruct' => 2000,  
        'ibm/granite-3.0-3b-a800m-instruct' => 2000,
        'nvidia/mistral-nemo-minitron-8b-8k-instruct' => 8000,
        'nvidia/llama-3.1-nemotron-70b-reward' => 4000,  
        'meta/llama-3.2-3b-instruct' => 4000,
        'meta/llama-3.2-1b-instruct' => 2000,
        'nvidia/llama-3.1-nemotron-51b-instruct' => 4000,  
        'qwen/qwen2-7b-instruct' => 2000,  
        'abacusai/dracarys-llama-3.1-70b-instruct' => 4000,  
        'ai21labs/jamba-1.5-mini-instruct' => 2000,
        'nvidia/nemotron-mini-4b-instruct' => 2000,  
        'microsoft/phi-3.5-moe-instruct' => 8000,  
        'microsoft/phi-3.5-mini-instruct' => 4000,  
        'mistralai/mathstral-7b-v0.1' => 2000,
        'rakuten/rakutenai-7b-instruct' => 2000,
        'rakuten/rakutenai-7b-chat' => 2000,
        'mistralai/mistral-large-2-instruct' => 4000,
        'writer/palmyra-fin-70b-32k' => 32000, 
        'thudm/chatglm3-6b' => 4000,
        'baichuan-inc/baichuan2-13b-chat' => 4000,
        'meta/llama-3.1-70b-instruct' => 4000,  
        'meta/llama-3.1-8b-instruct' => 4000,
        'nv-mistralai/mistral-nemo-12b-instruct' => 8000,
        'nvidia/llama3-chatqa-1.5-70b' => 1000,  
        'nvidia/llama3-chatqa-1.5-8b' => 1000,
        '01-ai/yi-large' => 4000,
        'nvidia/nemotron-4-340b-instruct' => 4000,  
        'writer/palmyra-med-70b-32k' => 32000,
        'writer/palmyra-med-70b' => 4000,
        'upstage/solar-10.7b-instruct' => 2000,
        'mediatek/breeze-7b-instruct' => 1000,
        'ibm/granite-34b-code-instruct' => 2000,
        'ibm/granite-8b-code-instruct' => 4000,
        'aisingapore/sea-lion-7b-instruct' => 1000,
        'microsoft/phi-3-mini-4k-instruct' => 4000,
        'mistralai/mixtral-8x22b-instruct-v0.1' => 4000,
        'meta/llama3-70b-instruct' => 4000,
        'meta/llama3-8b-instruct' => 4000,
        'google/codegemma-7b' => 2000,
        'google/gemma-2b' => 2000,
        'google/gemma-7b' => 2000,
        'meta/codellama-70b' => 1000,
        'mistralai/mixtral-8x7b-instruct-v0.1' => 4000
    );
}
function aiomatic_get_xai_model_tokens()
{
    return array(
        'grok-beta' => 128000
    );
}
function aiomatic_get_max_input_tokens_perplexity($model)
{
    static $perplexity_arr = AIOMATIC_PERPLEXITY_MODELS;
    if(!in_array($model, $perplexity_arr))
    {
        return AIOMATIC_DEFAULT_MAX_TOKENS;
    }
    $ppmodel_tokens = aiomatic_get_perplexity_model_tokens();
    if(isset($ppmodel_tokens[$model]))
    {
        return $ppmodel_tokens[$model];
    }
    return AIOMATIC_DEFAULT_MAX_TOKENS;
}
function aiomatic_get_max_input_tokens_groq($model)
{
    static $groq_arr = AIOMATIC_GROQ_MODELS;
    if(!in_array($model, $groq_arr))
    {
        return AIOMATIC_DEFAULT_MAX_TOKENS;
    }
    $ppmodel_tokens = aiomatic_get_groq_model_tokens();
    if(isset($ppmodel_tokens[$model]))
    {
        return $ppmodel_tokens[$model];
    }
    return AIOMATIC_DEFAULT_MAX_TOKENS;
}
function aiomatic_get_max_input_tokens_nvidia($model)
{
    static $nvidia_arr = AIOMATIC_NVIDIA_MODELS;
    if(!in_array($model, $nvidia_arr))
    {
        return AIOMATIC_DEFAULT_MAX_TOKENS;
    }
    $ppmodel_tokens = aiomatic_get_nvidia_model_tokens();
    if(isset($ppmodel_tokens[$model]))
    {
        return $ppmodel_tokens[$model];
    }
    return AIOMATIC_DEFAULT_MAX_TOKENS;
}
function aiomatic_get_max_input_tokens_xai($model)
{
    static $xai_arr = AIOMATIC_XAI_MODELS;
    if(!in_array($model, $xai_arr))
    {
        return AIOMATIC_DEFAULT_MAX_TOKENS;
    }
    $ppmodel_tokens = aiomatic_get_xai_model_tokens();
    if(isset($ppmodel_tokens[$model]))
    {
        return $ppmodel_tokens[$model];
    }
    return AIOMATIC_DEFAULT_MAX_TOKENS;
}
function aiomatic_get_max_output_tokens_openrouter($model)
{
    static $openrouter_arr = false;
    if($openrouter_arr === false)
    {
        try
        {
            $openrouter_arr = aiomatic_get_openrouter_models();
            if($openrouter_arr !== false)
            {
                foreach($openrouter_arr['source_list'] as $smodel)
                {
                    if($model === $smodel['model'])
                    {
                        return $smodel['maxCompletionTokens'];
                    }
                }
            }
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to list OpenRouter models: ' . $e->getMessage());
        }
    }
    return AIOMATIC_DEFAULT_MAX_TOKENS;
}
function aiomatic_sanitize_ai_result($generated_text)
{
    if(is_string($generated_text))
    {
        $generated_text = trim($generated_text);
        if (($generated_text[0] === '"' && $generated_text[strlen($generated_text) - 1] === '"') ||
            ($generated_text[0] === '\'' && $generated_text[strlen($generated_text) - 1] === '\'')) 
        {
            $generated_text = substr($generated_text, 1, -1);
            $generated_text = trim($generated_text);
        } 
        else 
        {
            $generated_text = $generated_text;
        }
    }
    return $generated_text;
}
function aiomatic_load_textdomain() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
    {
        if (isset($aiomatic_Main_Settings['assistant_disable']) && ($aiomatic_Main_Settings['assistant_disable'] == 'front' || $aiomatic_Main_Settings['assistant_disable'] == 'both'))
        {
            add_filter("mce_external_plugins", "aiomatic_enqueue_plugin_scripts");
            add_action('wp_head', 'aiomatic_classic_mce_inline_script_always');
            add_filter("mce_buttons", "aiomatic_register_buttons_editor");
        }
        
        if (isset($aiomatic_Main_Settings['enable_wpcli']) && trim($aiomatic_Main_Settings['enable_wpcli']) != '')
        {
            if ( defined( 'WP_CLI' ) && WP_CLI )
            {
                WP_CLI::add_command( 'aicontent', 'aiomatic_wpcli_add' );
            }
        }
        if (isset($aiomatic_Main_Settings['rest_api_init']) && trim($aiomatic_Main_Settings['rest_api_init']) != '')
        {
            add_action( 'rest_api_init', function () 
            {
                register_rest_route( 'aiomatic', 'v1/models', array(
                'methods' => ['GET', 'POST'],
                'callback' => 'aiomatic_rest_list_models',
                'permission_callback' => '__return_true'
                ) );
            });
            add_action( 'rest_api_init', function () 
            {
                register_rest_route( 'aiomatic', 'v1/assistants', array(
                'methods' => ['GET', 'POST'],
                'callback' => 'aiomatic_rest_list_assistants',
                'permission_callback' => '__return_true'
                ) );
            });
            add_action( 'rest_api_init', function () 
            {
                register_rest_route( 'aiomatic', 'v1/text', array(
                'methods' => ['GET', 'POST'],
                'callback' => 'aiomatic_rest_generate_text',
                'permission_callback' => '__return_true'
                ) );
            });
            add_action( 'rest_api_init', function () 
            {
                register_rest_route( 'aiomatic', 'v1/embeddings', array(
                'methods' => ['GET', 'POST'],
                'callback' => 'aiomatic_rest_generate_embedding',
                'permission_callback' => '__return_true'
                ) );
            });
            add_action( 'rest_api_init', function () 
            {
                register_rest_route( 'aiomatic', 'v1/image', array(
                'methods' => ['GET', 'POST'],
                'callback' => 'aiomatic_rest_generate_image',
                'permission_callback' => '__return_true'
                ) );
            });
        }
        $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
        $appids = array_filter($appids);
        $token = $appids[array_rand($appids)];
        $token = apply_filters('aiomatic_openai_api_key', $token);
        require_once(dirname(__FILE__) . "/res/Embeddings.php");
        new Aiomatic_Embeddings($token);
        if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] === 'on') 
        {
            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
            if (isset($aiomatic_Chatbot_Settings['enable_front_end']) && $aiomatic_Chatbot_Settings['enable_front_end'] != '') 
            {
                if(($aiomatic_Chatbot_Settings['enable_front_end'] === 'front' || $aiomatic_Chatbot_Settings['enable_front_end'] === 'both'))
                {
                    add_action( 'wp_footer', 'aiomatic_inject_chat' );
                }
                if(($aiomatic_Chatbot_Settings['enable_front_end'] === 'back' || $aiomatic_Chatbot_Settings['enable_front_end'] === 'both'))
                {
                    add_action( 'admin_footer', 'aiomatic_inject_chat_admin' );
                }
            }
        }
    }
    load_plugin_textdomain( 'aiomatic-automatic-ai-content-writer', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}
add_action( 'init', 'aiomatic_load_textdomain' );

function aiomatic_rest_list_models() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $err = array();
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') 
    {
        if (isset($aiomatic_Main_Settings['rest_api_init']) && $aiomatic_Main_Settings['rest_api_init'] == 'on') 
        {
            if (isset($aiomatic_Main_Settings['rest_api_keys']) && trim($aiomatic_Main_Settings['rest_api_keys']) != '') 
            {
                $api_key = '';
                if(isset($_GET['apikey']))
                {
                    $api_key = trim($_GET['apikey']);
                }
                elseif(isset($_POST['apikey']))
                {
                    $api_key = trim($_POST['apikey']);
                }
                $rest_api_keys = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['rest_api_keys']));
                $rest_api_keys = array_map('trim', $rest_api_keys);
                $rest_api_keys = array_filter($rest_api_keys);
                if(empty($api_key))
                {
                    $err['success'] = false;
                    $err['error'] = 'You need to specify an API key for this request';
                    return $err;
                }
                else
                {
                    if(!in_array($api_key, $rest_api_keys))
                    {
                        $err['success'] = false;
                        $err['error'] = 'Invalid API key provided';
                        return $err;
                    }
                }
            }
            $all_models = aiomatic_get_all_models(true);
            $err['success'] = true;
            $err['models'] = $all_models;
            return $err;
        } 
        else 
        {
            $err['success'] = false;
            $err['error'] = 'Aiomatic REST API not enabled';
            return $err;
        }
    }
    else 
    {
        $err['success'] = false;
        $err['error'] = 'Aiomatic not enabled';
        return $err;
    }
}
function aiomatic_rest_list_assistants() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $err = array();
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') 
    {
        if (isset($aiomatic_Main_Settings['rest_api_init']) && $aiomatic_Main_Settings['rest_api_init'] == 'on') 
        {
            if (isset($aiomatic_Main_Settings['rest_api_keys']) && trim($aiomatic_Main_Settings['rest_api_keys']) != '') 
            {
                $api_key = '';
                if(isset($_GET['apikey']))
                {
                    $api_key = trim($_GET['apikey']);
                }
                elseif(isset($_POST['apikey']))
                {
                    $api_key = trim($_POST['apikey']);
                }
                $rest_api_keys = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['rest_api_keys']));
                $rest_api_keys = array_map('trim', $rest_api_keys);
                $rest_api_keys = array_filter($rest_api_keys);
                if(empty($api_key))
                {
                    $err['success'] = false;
                    $err['error'] = 'You need to specify an API key for this request';
                    return $err;
                }
                else
                {
                    if(!in_array($api_key, $rest_api_keys))
                    {
                        $err['success'] = false;
                        $err['error'] = 'Invalid API key provided';
                        return $err;
                    }
                }
            }
            $all_assistants = aiomatic_get_all_assistants(true);
            $assarra = array();
            if(is_array($all_assistants))
            {
                foreach($all_assistants as $tas)
                {
                    $assarra[$tas->ID] = $tas->post_title;
                }
            }
            $err['success'] = true;
            $err['assistants'] = $assarra;
            return $err;
        } 
        else 
        {
            $err['success'] = false;
            $err['error'] = 'Aiomatic REST API not enabled';
            return $err;
        }
    }
    else 
    {
        $err['success'] = false;
        $err['error'] = 'Aiomatic not enabled';
        return $err;
    }
}
function aiomatic_rest_generate_text() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $err = array();
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') 
    {
        if (isset($aiomatic_Main_Settings['rest_api_init']) && $aiomatic_Main_Settings['rest_api_init'] == 'on') 
        {
            if (isset($aiomatic_Main_Settings['rest_api_keys']) && trim($aiomatic_Main_Settings['rest_api_keys']) != '') 
            {
                $api_key = '';
                if(isset($_GET['apikey']))
                {
                    $api_key = trim($_GET['apikey']);
                }
                elseif(isset($_POST['apikey']))
                {
                    $api_key = trim($_POST['apikey']);
                }
                $rest_api_keys = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['rest_api_keys']));
                $rest_api_keys = array_map('trim', $rest_api_keys);
                $rest_api_keys = array_filter($rest_api_keys);
                if(empty($api_key))
                {
                    $err['success'] = false;
                    $err['error'] = 'You need to specify an API key for this request';
                    return $err;
                }
                else
                {
                    if(!in_array($api_key, $rest_api_keys))
                    {
                        $err['success'] = false;
                        $err['error'] = 'Invalid API key provided';
                        return $err;
                    }
                }
            }
            if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
            {
                if(!isset($_REQUEST['prompt']))
                {
                    $err['success'] = false;
                    $err['error'] = 'Parameter missing: prompt';
                    return $err;
                }
                if(isset($_REQUEST['model']))
                {
                    $model = $_REQUEST['model'];
                }
                else
                {
                    $model = AIOMATIC_DEFAULT_MODEL;
                }
                if(isset($_REQUEST['assistant']))
                {
                    $assistant = $_REQUEST['assistant'];
                }
                else
                {
                    $assistant = '';
                }
                $prompt = $_REQUEST['prompt'];
                $all_models = aiomatic_get_all_models(true);
                if(!in_array($model, $all_models))
                {
                    $model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
                }
                $query_token_count = count(aiomatic_encode($prompt));
                $max_tokens = aiomatic_get_max_tokens($model);
                $available_tokens = aiomatic_compute_available_tokens($model, $max_tokens, $prompt, $query_token_count);
                if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
                {
                    $string_len = strlen($prompt);
                    $string_len = $string_len / 2;
                    $string_len = intval(0 - $string_len);
                    $prompt = aiomatic_substr($prompt, 0, $string_len);
                    $prompt = trim($prompt);
                    $query_token_count = count(aiomatic_encode($prompt));
                    $available_tokens = $max_tokens - $query_token_count;
                }
                if(!empty($prompt))
                {
                    $GLOBALS['aiomatic_stats'] = new Aiomatic_Statistics();
                    $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
                    $appids = array_filter($appids);
                    $token = $appids[array_rand($appids)];
                    $token = apply_filters('aiomatic_openai_api_key', $token);
                    $thread_id = '';
                    $aierror = '';
                    $finish_reason = '';
                    $generated_text = aiomatic_generate_text($token, $model, $prompt, $available_tokens, 1, 1, 0, 0, false, 'api', 0, $finish_reason, $aierror, false, false, false, '', '', 'user', $assistant, $thread_id, '', 'disabled', '', false, false);
                    if($generated_text === false)
                    {
                        $err['success'] = false;
                        $err['error'] = 'Failed to generate the AI reply, error: ' . $aierror;
                        return $err;
                    }
                    else
                    {
                        $generated_text = aiomatic_sanitize_ai_result($generated_text);
                        if(empty($generated_text))
                        {
                            $err['success'] = false;
                            $err['error'] = 'Empty AI response returned!';
                            return $err;
                        }
                        else
                        {
                            $err['success'] = true;
                            $err['data'] = $generated_text;
                            $err['input_tokens'] = $query_token_count;
                            $result_token_count = count(aiomatic_encode($generated_text));
                            $err['output_tokens'] = $result_token_count;
                            return $err;
                        }
                    }
                }
                else
                {
                    $err['success'] = false;
                    $err['error'] = 'Empty AI prompt provided!';
                    return $err;
                }
            }
            else
            {
                $err['success'] = false;
                $err['error'] = 'You need to add an AI API key in the Aiomatic plugin\'s settings for this to work!';
                return $err;
            }
        } 
        else 
        {
            $err['success'] = false;
            $err['error'] = 'Aiomatic REST API not enabled';
            return $err;
        }
    }
    else 
    {
        $err['success'] = false;
        $err['error'] = 'Aiomatic not enabled';
        return $err;
    }
}
function aiomatic_rest_generate_image() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $err = array();
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') 
    {
        if (isset($aiomatic_Main_Settings['rest_api_init']) && $aiomatic_Main_Settings['rest_api_init'] == 'on') 
        {
            if (isset($aiomatic_Main_Settings['rest_api_keys']) && trim($aiomatic_Main_Settings['rest_api_keys']) != '') 
            {
                $api_key = '';
                if(isset($_GET['apikey']))
                {
                    $api_key = trim($_GET['apikey']);
                }
                elseif(isset($_POST['apikey']))
                {
                    $api_key = trim($_POST['apikey']);
                }
                $rest_api_keys = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['rest_api_keys']));
                $rest_api_keys = array_map('trim', $rest_api_keys);
                $rest_api_keys = array_filter($rest_api_keys);
                if(empty($api_key))
                {
                    $err['success'] = false;
                    $err['error'] = 'You need to specify an API key for this request';
                    return $err;
                }
                else
                {
                    if(!in_array($api_key, $rest_api_keys))
                    {
                        $err['success'] = false;
                        $err['error'] = 'Invalid API key provided';
                        return $err;
                    }
                }
            }
            if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
            {
                if(!isset($_REQUEST['prompt']))
                {
                    $err['success'] = false;
                    $err['error'] = 'Parameter missing: prompt';
                    return $err;
                }
                if(isset($_REQUEST['model']))
                {
                    $model = $_REQUEST['model'];
                }
                else
                {
                    $model = AIOMATIC_DEFAULT_IMAGE_MODEL;
                }
                $prompt = $_REQUEST['prompt'];
                $all_models = AIOMATIC_DALLE_IMAGE_MODELS;
                if(!in_array($model, $all_models))
                {
                    $model = AIOMATIC_DEFAULT_IMAGE_MODEL;
                }
                if(!empty($prompt))
                {
                    $GLOBALS['aiomatic_stats'] = new Aiomatic_Statistics();
                    $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
                    $appids = array_filter($appids);
                    $token = $appids[array_rand($appids)];
                    $token = apply_filters('aiomatic_openai_api_key', $token);
                    $aierror = '';
                    $image_size = '1024x1024';
                    $result = aiomatic_generate_ai_image($token, 1, $prompt, $image_size, 'apiImage', true, 0, $aierror, $model);
                    if($result === false)
                    {
                        $err['success'] = false;
                        $err['error'] = 'Failed to generate the AI reply, error: ' . $aierror;
                        return $err;
                    }
                    else
                    {
                        $err['success'] = true;
                        $err['data'] = $result[0];
                        return $err;
                    }
                }
                else
                {
                    $err['success'] = false;
                    $err['error'] = 'Empty AI prompt provided!';
                    return $err;
                }
            }
            else
            {
                $err['success'] = false;
                $err['error'] = 'You need to add an AI API key in the Aiomatic plugin\'s settings for this to work!';
                return $err;
            }
        } 
        else 
        {
            $err['success'] = false;
            $err['error'] = 'Aiomatic REST API not enabled';
            return $err;
        }
    }
    else 
    {
        $err['success'] = false;
        $err['error'] = 'Aiomatic not enabled';
        return $err;
    }
}
function aiomatic_rest_generate_embedding() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $err = array();
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') 
    {
        if (isset($aiomatic_Main_Settings['rest_api_init']) && $aiomatic_Main_Settings['rest_api_init'] == 'on') 
        {
            if (isset($aiomatic_Main_Settings['rest_api_keys']) && trim($aiomatic_Main_Settings['rest_api_keys']) != '') 
            {
                $api_key = '';
                if(isset($_GET['apikey']))
                {
                    $api_key = trim($_GET['apikey']);
                }
                elseif(isset($_POST['apikey']))
                {
                    $api_key = trim($_POST['apikey']);
                }
                $rest_api_keys = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['rest_api_keys']));
                $rest_api_keys = array_map('trim', $rest_api_keys);
                $rest_api_keys = array_filter($rest_api_keys);
                if(empty($api_key))
                {
                    $err['success'] = false;
                    $err['error'] = 'You need to specify an API key for this request';
                    return $err;
                }
                else
                {
                    if(!in_array($api_key, $rest_api_keys))
                    {
                        $err['success'] = false;
                        $err['error'] = 'Invalid API key provided';
                        return $err;
                    }
                }
            }
            if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
            {
                if(!isset($_REQUEST['prompt']))
                {
                    $err['success'] = false;
                    $err['error'] = 'Parameter missing: prompt';
                    return $err;
                }
                if(isset($_REQUEST['model']))
                {
                    $model = $_REQUEST['model'];
                }
                else
                {
                    $model = AIOMATIC_DEFAULT_MODEL_EMBEDDING;
                }
                $prompt = $_REQUEST['prompt'];
                $all_models = AIOMATIC_EMBEDDINGS_MODELS;
                if(!in_array($model, $all_models))
                {
                    $model = AIOMATIC_DEFAULT_MODEL_EMBEDDING;
                }
                if(!empty($prompt))
                {
                    $GLOBALS['aiomatic_stats'] = new Aiomatic_Statistics();
                    $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
                    $appids = array_filter($appids);
                    $token = $appids[array_rand($appids)];
                    $token = apply_filters('aiomatic_openai_api_key', $token);
                    require_once(dirname(__FILE__) . "/res/Embeddings.php");
                    $embdedding = new Aiomatic_Embeddings($token);
                    $result = $embdedding->aiomatic_get_embedding_data($prompt, $model);
                    return $result;
                }
                else
                {
                    $err['success'] = false;
                    $err['error'] = 'Empty AI prompt provided!';
                    return $err;
                }
            }
            else
            {
                $err['success'] = false;
                $err['error'] = 'You need to add an AI API key in the Aiomatic plugin\'s settings for this to work!';
                return $err;
            }
        } 
        else 
        {
            $err['success'] = false;
            $err['error'] = 'Aiomatic REST API not enabled';
            return $err;
        }
    }
    else 
    {
        $err['success'] = false;
        $err['error'] = 'Aiomatic not enabled';
        return $err;
    }
}
function aiomatic_check_additional_chats(&$chatfound, $aiomatic_Chatbot_Settings)
{
    if(isset($aiomatic_Chatbot_Settings['aiomatic_chat_json']) && !empty(isset($aiomatic_Chatbot_Settings['aiomatic_chat_json'])))
    {
        $jsdec = json_decode($aiomatic_Chatbot_Settings['aiomatic_chat_json']);
        if($jsdec != null)
        {
            foreach($jsdec as $dataid => $value)
            {
                $data = $value->data;
                if (isset($data->not_show_urls) && trim($data->not_show_urls) != '') 
                {
                    $no_show_urls = preg_split('/\r\n|\r|\n/', trim($data->not_show_urls));
                    $no_show_urls = array_filter($no_show_urls);
                    if(count($no_show_urls) > 0)
                    {
                        global $wp;
                        $current_url = home_url( $wp->request );
                        foreach($no_show_urls as $nsurl)
                        {
                            if(rtrim($current_url, '/') == rtrim(trim($nsurl), '/'))
                            {
                                continue;
                            }
                        }
                    }
                }
                if (isset($data->only_show_urls) && trim($data->only_show_urls) != '') 
                {
                    $only_show_urls = preg_split('/\r\n|\r|\n/', trim($data->only_show_urls));
                    $only_show_urls = array_filter($only_show_urls);
                    if(count($only_show_urls) > 0)
                    {
                        $url_found = false;
                        global $wp;
                        $current_url = home_url( $wp->request );
                        foreach($only_show_urls as $nsurl)
                        {
                            if(rtrim($current_url, '/') == rtrim(trim($nsurl), '/'))
                            {
                                $url_found = true;
                            }
                        }
                        if($url_found === false)
                        {
                            continue;
                        }
                    }
                }
                if (isset($data->never_show) && is_array($data->never_show)) 
                {
                    $this_day = date('l');
                    if(in_array($this_day, $data->never_show))
                    {
                        continue;
                    }
                }
                if (isset($data->show_content_wp) && is_array($data->show_content_wp) && !empty($data->show_content_wp)) 
                {
                    $post_chars = aiomatic_get_post_characteristics();
                    $fnd = false;
                    foreach($data->show_content_wp as $showme)
                    {
                        if(in_array($showme, $post_chars))
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == false)
                    {
                        continue;
                    }
                }
                if (isset($data->no_show_content_wp) && is_array($data->no_show_content_wp) && !empty($data->no_show_content_wp)) 
                {
                    $post_chars = aiomatic_get_post_characteristics();
                    $fnd = false;
                    foreach($data->no_show_content_wp as $showme)
                    {
                        if(in_array($showme, $post_chars))
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == true)
                    {
                        continue;
                    }
                }
                if (isset($data->no_show_locales) && is_array($data->no_show_locales) && !empty($data->no_show_locales)) 
                {
                    $locale   = get_user_locale();
                    $fnd = false;
                    foreach($data->no_show_locales as $showme)
                    {
                        if($showme == $locale)
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == true)
                    {
                        continue;
                    }
                }
                if (isset($data->show_locales) && is_array($data->show_locales) && !empty($data->show_locales)) 
                {
                    $locale   = get_user_locale();
                    $fnd = false;
                    foreach($data->show_locales as $showme)
                    {
                        if($showme == $locale)
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == false)
                    {
                        continue;
                    }
                }
                if (isset($data->no_show_roles) && is_array($data->no_show_roles) && !empty($data->no_show_roles)) 
                {
                    $user   = wp_get_current_user();
                    $fnd = false;
                    if ( null !== $user ) 
                    { 
                        foreach ( $user->roles as $role ) {
                            if ( in_array( $role, $data->no_show_roles, true ) ) {
                                $fnd = true;
                                break;
                            }
                        }
                        if($fnd == true)
                        {
                            continue;
                        }
                    }
                }
                if (isset($data->show_roles) && is_array($data->show_roles) && !empty($data->show_roles)) 
                {
                    $user   = wp_get_current_user();
                    $fnd = false;
                    if ( null !== $user ) 
                    { 
                        foreach ( $user->roles as $role ) {
                            if ( in_array( $role, $data->show_roles, true ) ) {
                                $fnd = true;
                                break;
                            }
                        }
                    }
                    if($fnd == false)
                    {
                        continue;
                    }
                }
                if (isset($data->no_show_devices) && is_array($data->no_show_devices) && !empty($data->no_show_devices)) 
                {
                    require_once(dirname(__FILE__) . "/res/mobile-detect.php");
                    $fnd = false;
                    $detect = new AiomaticMobileDetect;
                    $device = 'desktop';
                    if ( $detect->isTablet() ) {
                        $device = 'tablet';
                    }
                    if ( $detect->isMobile() && ! $detect->isTablet() ) {
                        $device = 'mobile';
                    }
                    if ( in_array( $device, $data->no_show_devices, true ) ) 
                    {
                        $fnd = true;
                    }
                    if($fnd == true)
                    {
                        continue;
                    }
                }
                if (isset($data->show_devices) && is_array($data->show_devices) && !empty($data->show_devices)) 
                {
                    require_once(dirname(__FILE__) . "/res/mobile-detect.php");
                    $fnd = false;
                    $detect = new AiomaticMobileDetect;
                    $device = 'desktop';
                    if ( $detect->isTablet() ) {
                        $device = 'tablet';
                    }
                    if ( $detect->isMobile() && ! $detect->isTablet() ) {
                        $device = 'mobile';
                    }
                    if ( in_array( $device, $data->show_devices, true ) ) 
                    {
                        $fnd = true;
                    }
                    if($fnd == false)
                    {
                        continue;
                    }
                }
                if (isset($data->no_show_oses) && is_array($data->no_show_oses) && !empty($data->no_show_oses)) 
                {
                    $fnd = false;
                    foreach($data->no_show_oses as $showme)
                    {
                        if ( aiomatic_detectOS($showme) ) 
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == true)
                    {
                        continue;
                    }
                }
                if (isset($data->show_oses) && is_array($data->show_oses) && !empty($data->show_oses)) 
                {
                    $fnd = false;
                    foreach($data->show_oses as $showme)
                    {
                        if ( aiomatic_detectOS($showme) ) 
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == false)
                    {
                        continue;
                    }
                }
                if (isset($data->no_show_browsers) && is_array($data->no_show_browsers) && !empty($data->no_show_browsers)) 
                {
                    $fnd = false;
                    foreach($data->no_show_browsers as $showme)
                    {
                        if ( aiomatic_detectBrowser($showme) ) 
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == true)
                    {
                        continue;
                    }
                }
                if (isset($data->show_browsers) && is_array($data->show_browsers) && !empty($data->show_browsers)) 
                {
                    $fnd = false;
                    foreach($data->show_browsers as $showme)
                    {
                        if ( aiomatic_detectBrowser($showme) ) 
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == false)
                    {
                        continue;
                    }
                }
                if (isset($data->show_ips) && !empty($data->show_ips)) 
                {
                    $fnd = false;
                    $sips = preg_split('/\r\n|\r|\n/', $data->show_ips);
                    foreach($sips as $showme)
                    {
                        if ( aiomatic_passIPs($showme) ) 
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == false)
                    {
                        continue;
                    }
                }
                if (isset($data->no_show_ips) && !empty($data->no_show_ips)) 
                {
                    $fnd = false;
                    $sips = preg_split('/\r\n|\r|\n/', $data->no_show_ips);
                    foreach($sips as $showme)
                    {
                        if ( aiomatic_passIPs($showme) ) 
                        {
                            $fnd = true;
                            break;
                        }
                    }
                    if($fnd == true)
                    {
                        continue;
                    }
                }
                if (isset($data->min_time) && $data->min_time != '' && isset($data->max_time) && $data->max_time != '') 
                {
                    $always_show = false;
                    if (isset($data->always_show) && is_array($data->always_show)) 
                    {
                        $this_day = date('l');
                        if(in_array($this_day, $data->always_show))
                        {
                            $always_show = true;
                        }
                    }
                    if($always_show === false)
                    {
                        $exit = true;
                        $mytime = date("H:i");
                        $min_time = $data->min_time;
                        $max_time = $data->max_time;
                        $date1 = DateTime::createFromFormat('H:i', $mytime);
                        $date2 = DateTime::createFromFormat('H:i', $min_time);
                        $date3 = DateTime::createFromFormat('H:i', $max_time);
                        if ($date1 > $date2 && $date1 < $date3)
                        {
                            $exit = false;
                        }
                        if($exit == true)
                        {
                            continue;
                        }
                    }
                }
                $shortcode = $data->shortcode;
                if(trim($shortcode) != '')
                {
                    $global_chat_params = array( 'temperature' => '', 'top_p' => '', 'presence_penalty' => '', 'frequency_penalty' => '', 'model' => '', 'instant_response' => '', 'show_in_window' => 'true', 'disable_filters' => '1' );
                    $temp_arr = shortcode_parse_atts(stripslashes(trim($shortcode)));
                    if(!empty($temp_arr))
                    {
                        $global_chat_params = $temp_arr;
                        $global_chat_params['show_in_window'] = 'true';
                        $global_chat_params['disable_filters'] = '1';
                    }
                    $chatrez = aiomatic_chat_shortcode($global_chat_params);
                    if(!empty($chatrez))
                    {
                        $chatfound = true;
                        echo $chatrez;
                        break;
                    }
                }
            }
        }
    }
}
function aiomatic_inject_chat()
{
    $chatfound = false;
    $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
    aiomatic_check_additional_chats($chatfound, $aiomatic_Chatbot_Settings);
    if($chatfound === false)
    {
        $global_chat_params = array( 'temperature' => '', 'top_p' => '', 'presence_penalty' => '', 'frequency_penalty' => '', 'model' => '', 'instant_response' => '', 'show_in_window' => 'true' );
        if (isset($aiomatic_Chatbot_Settings['custom_global_shortcode']) && trim($aiomatic_Chatbot_Settings['custom_global_shortcode']) != '')
        {
            $temp_arr = shortcode_parse_atts(trim($aiomatic_Chatbot_Settings['custom_global_shortcode']));
            if(!empty($temp_arr))
            {
                $global_chat_params = $temp_arr;
                $global_chat_params['show_in_window'] = 'true';
            }
        }
        if (isset($aiomatic_Chatbot_Settings['god_mode_front_end']) && (trim($aiomatic_Chatbot_Settings['god_mode_front_end']) == 'front' || trim($aiomatic_Chatbot_Settings['god_mode_front_end']) == 'both'))
        {
            $global_chat_params['enable_god_mode'] = 'enabled';
        }
        echo aiomatic_chat_shortcode($global_chat_params);
    }
}
function aiomatic_inject_chat_admin()
{
    $chatfound = false;
    $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
    aiomatic_check_additional_chats($chatfound, $aiomatic_Chatbot_Settings);
    if($chatfound === false)
    {
        $global_chat_params = array( 'temperature' => '', 'top_p' => '', 'presence_penalty' => '', 'frequency_penalty' => '', 'model' => '', 'instant_response' => '', 'show_in_window' => 'true' );
        if (isset($aiomatic_Chatbot_Settings['custom_global_shortcode']) && trim($aiomatic_Chatbot_Settings['custom_global_shortcode']) != '')
        {
            $temp_arr = shortcode_parse_atts(trim($aiomatic_Chatbot_Settings['custom_global_shortcode']));
            if(!empty($temp_arr))
            {
                $global_chat_params = $temp_arr;
                $global_chat_params['show_in_window'] = 'true';
            }
        }
        if (isset($aiomatic_Chatbot_Settings['god_mode_front_end']) && (trim($aiomatic_Chatbot_Settings['god_mode_front_end']) == 'back' || trim($aiomatic_Chatbot_Settings['god_mode_front_end']) == 'both'))
        {
            $global_chat_params['enable_god_mode'] = 'enabled';
        }
        echo aiomatic_chat_shortcode($global_chat_params);
    }
}

function aiomatic_is_gutenberg() 
{
    if(isset($GLOBALS['post']->ID) && function_exists('has_blocks') && has_blocks($GLOBALS['post']->ID))
    {
        return true;    
    } 
    else 
    {
        return false;
    }
}
function aiomatic_enqueue_plugin_scripts($plugin_array)
{
    $plugin_array["aiomatic_editor"] =  plugin_dir_url(__FILE__) . "scripts/classic-editor.js";
    return $plugin_array;
}

function aiomatic_register_buttons_editor($buttons)
{
    array_push($buttons, "aiomatic");
    return $buttons;
}
function aiomatic_classic_mce_inline_script_always() 
{
	aiomatic_add_inline_js_object();
}
function aiomatic_classic_mce_inline_script() 
{
    global $pagenow;
    if ($pagenow !== 'post.php' && $pagenow !== 'post-new.php' && $pagenow !== 'admin.php') {
        return;
    }
	aiomatic_add_inline_js_object();
}
function aiomatic_add_inline_js_object () 
{
    $aiomatic_build_plugin_js_config = aiomatic_build_plugin_js_config();
    $name = md5(get_bloginfo());
    wp_enqueue_script($name . '-admin-footer-script', plugins_url('scripts/admin-footer.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
	wp_add_inline_script( $name . '-admin-footer-script', 'var aiomatic = ' . json_encode($aiomatic_build_plugin_js_config) );
}
function aiomatic_build_plugin_js_config() 
{
    $assistant_placement = 'below';
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['assistant_placement']) && $aiomatic_Main_Settings['assistant_placement'] != '') 
    {
        $assistant_placement = $aiomatic_Main_Settings['assistant_placement'];
    }
    $nonce = wp_create_nonce('wp_rest' );
    $prompts  = aiomatic_get_assistant();
    if(!is_array($prompts))
    {
        $prompts = array();
    }
    $aiomaticScriptVars = array(
        'nonce'  =>  $nonce,
        'ajaxurl' => admin_url('admin-ajax.php'),
        'prompts' => $prompts,
        'placement' => $assistant_placement,
        'xicon' => plugins_url('/images/icon.png', __FILE__)
    );
    return $aiomaticScriptVars;
}

$plugin = plugin_basename(__FILE__);
if(is_admin())
{
    if(!aiomatic_is_gutenberg())
    {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on')
        {
            if (!isset($aiomatic_Main_Settings['assistant_disable']) || ($aiomatic_Main_Settings['assistant_disable'] == 'back' || $aiomatic_Main_Settings['assistant_disable'] == 'both'))
            {
                add_filter("mce_external_plugins", "aiomatic_enqueue_plugin_scripts");
                add_action('admin_head', 'aiomatic_classic_mce_inline_script');
                add_filter("mce_buttons", "aiomatic_register_buttons_editor");
            }
        }
    }
    if($_SERVER["REQUEST_METHOD"]==="POST" && !empty($_POST["coderevolution_max_input_var_data"])) 
    {
        $vars = explode("&", $_POST["coderevolution_max_input_var_data"]);
        $coderevolution_max_input_var_data = array();
        foreach($vars as $var) {
            parse_str($var, $variable);
            aiomatic_assign_var($_POST, $variable, true);
        }
        unset($_POST["coderevolution_max_input_var_data"]);
    }
    require(dirname(__FILE__) . "/res/aiomatic-rules-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-listicle-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-youtube-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-amazon-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-review-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-csv-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-automation-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-single-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-spinner-list.php");
    require(dirname(__FILE__) . "/res/aiomatic-playground.php");
    require(dirname(__FILE__) . "/res/aiomatic-images.php");
    require(dirname(__FILE__) . "/res/aiomatic-chatbot.php");
    require(dirname(__FILE__) . "/res/aiomatic-shortcodes.php");
    require(dirname(__FILE__) . "/res/aiomatic-training.php");
    require(dirname(__FILE__) . "/res/aiomatic-assistants.php");
    require(dirname(__FILE__) . "/res/aiomatic-batch.php");
    require(dirname(__FILE__) . "/res/aiomatic-embeddings.php");
    require(dirname(__FILE__) . "/res/aiomatic-limits-statistics.php");
    require(dirname(__FILE__) . "/res/aiomatic-more.php");
    require(dirname(__FILE__) . "/res/aiomatic-extensions.php");
    require(dirname(__FILE__) . "/res/aiomatic-logs.php");
}
function aiomatic_admin_enqueue_all()
{
    $name = md5(get_bloginfo());
    $reg_css_code = '.cr_auto_update{background-color:#fff8e5;margin:5px 20px 15px 20px;border-left:4px solid #fff;padding:12px 12px 12px 12px !important;border-left-color:#ffb900;}';
    wp_register_style( $name . '-plugin-reg-style', false, false, AIOMATIC_MAJOR_VERSION );
    wp_enqueue_style( $name . '-plugin-reg-style' );
    wp_add_inline_style( $name . '-plugin-reg-style', $reg_css_code );
}
function aiomatic_add_activation_link($links)
{
    $settings_link = '<a href="admin.php?page=aiomatic_admin_settings">' . esc_html__('Activate Plugin License', 'aiomatic-automatic-ai-content-writer') . '</a>';
    array_push($links, $settings_link);
    return $links;
}
add_action('admin_menu', 'aiomatic_register_my_custom_menu_page');
add_action('network_admin_menu', 'aiomatic_register_my_custom_menu_page');
function aiomatic_register_my_custom_menu_page()
{
    $plugin = plugin_basename(__FILE__);
    $plugin_slug = explode('/', $plugin);
    $plugin_slug = $plugin_slug[0];
    $uoptions = array();
    $is_activated = aiomatic_is_activated($plugin_slug, $uoptions);
    if($is_activated === true || $is_activated === 2)
    {
        require(dirname(__FILE__) . "/res/aiomatic-main.php");
        $skip_main = false;
        $skip_omni = false;
        $skip_single = false;
        $skip_bulk = false;
        $skip_editor = false;
        $skip_chatbot = false;
        $skip_assistant = false;
        $skip_forms = false;
        $skip_embeddings = false;
        $skip_batch = false;
        $skip_training = false;
        $skip_playground = false;
        $skip_limits = false;
        $skip_more = false;
        $skip_ext = false;
        $skip_logs = false;
        $aiomatic_Menu_Rules = get_option('aiomatic_Menu_Rules', array());
        $base_slug = 'aiomatic_admin_settings';
        if(count($aiomatic_Menu_Rules) > 0)
        {
            $userid = get_current_user_id();
            if($userid > 0)
            {
                $user = new WP_User( $userid );
                if ( !empty( $user->roles ) && is_array( $user->roles ) ) 
                {
                    $current_roles = $user->roles;
                    foreach($aiomatic_Menu_Rules as $menu_rule)
                    {
                        if(isset($menu_rule[0]) && in_array($menu_rule[0], $current_roles) && isset($menu_rule[1]) && is_array($menu_rule[1]) && !empty($menu_rule[1]))
                        {
                            $base_slug = $menu_rule[1][0];
                            if(!in_array('aiomatic_admin_settings', $menu_rule[1]))
                            {
                                $skip_main = true;
                            }
                            if(!in_array('aiomatic_omniblocks', $menu_rule[1]))
                            {
                                $skip_omni = true;
                            }
                            if(!in_array('aiomatic_single_panel', $menu_rule[1]))
                            {
                                $skip_single = true;
                            }
                            if(!in_array('aiomatic_bulk_creators', $menu_rule[1]))
                            {
                                $skip_bulk = true;
                            }
                            if(!in_array('aiomatic_spinner_panel', $menu_rule[1]))
                            {
                                $skip_editor = true;
                            }
                            if(!in_array('aiomatic_chatbot_panel', $menu_rule[1]))
                            {
                                $skip_assistant = true;
                            }
                            if(!in_array('aiomatic_assistants_panel', $menu_rule[1]))
                            {
                                $skip_chatbot = true;
                            }
                            if(!in_array('aiomatic_shortcodes_panel', $menu_rule[1]))
                            {
                                $skip_forms = true;
                            }
                            if(!in_array('aiomatic_embeddings_panel', $menu_rule[1]))
                            {
                                $skip_embeddings = true;
                            }
                            if(!in_array('aiomatic_batch_panel', $menu_rule[1]))
                            {
                                $skip_batch = true;
                            }
                            if(!in_array('aiomatic_openai_training', $menu_rule[1]))
                            {
                                $skip_training = true;
                            }
                            if(!in_array('aiomatic_playground_panel', $menu_rule[1]))
                            {
                                $skip_playground = true;
                            }
                            if(!in_array('aiomatic_openai_status', $menu_rule[1]) && $menu_rule[0] != 'administrator')
                            {
                                $skip_limits = true;
                            }
                            if(!in_array('aiomatic_more', $menu_rule[1]))
                            {
                                $skip_more = true;
                            }
                            if(!in_array('aiomatic_extensions', $menu_rule[1]))
                            {
                                $skip_ext = true;
                            }
                            if(!in_array('aiomatic_logs', $menu_rule[1]))
                            {
                                $skip_logs = true;
                            }
                        }
                    }
                }
            }
        }
        add_menu_page('Aiomatic AI Content Writer, Editor & Chatbot', 'Aiomatic', 'access_aiomatic_menu', $base_slug, $base_slug, plugins_url('images/icon.png', __FILE__));
        if($skip_main == false)
        {
            $main = add_submenu_page('aiomatic_admin_settings', esc_html__("Settings", 'aiomatic-automatic-ai-content-writer'), esc_html__("Settings", 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_admin_settings');
            add_action( 'load-' . $main, 'aiomatic_load_all_admin_js' );
            add_action( 'load-' . $main, 'aiomatic_load_main_admin_js' );
            add_action( 'load-' . $main, 'aiomatic_load_playground' );
        }
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', array());
        if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') 
        {
            if($skip_single == false)
            {
                $single = add_submenu_page($base_slug, esc_html__('Single AI Post Creator', 'aiomatic-automatic-ai-content-writer'), esc_html__('Single AI Post Creator', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_single_panel', 'aiomatic_single_panel');
                add_action( 'load-' . $single, 'aiomatic_load_admin_js' );
                add_action( 'load-' . $single, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $single, 'aiomatic_load_single' );
                add_action( 'load-' . $single, 'aiomatic_load_playground' );
            }
            if($skip_bulk == false)
            {
                $merged = add_submenu_page($base_slug, esc_html__('Bulk AI Post Creators', 'aiomatic-automatic-ai-content-writer'), esc_html__('Bulk AI Post Creators', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_bulk_creators', 'aiomatic_bulk_creators');
                add_action( 'load-' . $merged, 'aiomatic_load_admin_js' );
                add_action( 'load-' . $merged, 'aiomatic_load_all_admin_js' );
            }
            if($skip_omni == false)
            {
                $omniblocks = add_submenu_page($base_slug, esc_html__('AI OmniBlocks', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI OmniBlocks', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_omniblocks', 'aiomatic_omniblocks');
                add_action( 'load-' . $omniblocks, 'aiomatic_load_admin_js' );
                add_action( 'load-' . $omniblocks, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $omniblocks, 'aiomatic_load_omni' );
                add_action( 'load-' . $omniblocks, 'aiomatic_load_playground' );
                add_action( 'load-' . $omniblocks, 'aiomatic_load_magic' );
            }
            if($skip_editor == false)
            {
                $auto = add_submenu_page($base_slug, esc_html__('AI Content Editor', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI Content Editor', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_spinner_panel', 'aiomatic_spinner_panel');
                add_action( 'load-' . $auto, 'aiomatic_load_post_admin_js' );
                add_action( 'load-' . $auto, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $auto, 'aiomatic_load_playground' );
                add_action( 'load-' . $auto, 'aiomatic_load_auto_rules_css' );
                add_action( 'load-' . $auto, 'aiomatic_load_spin' );
            }
            if($skip_chatbot == false)
            {
                $chatbot = add_submenu_page($base_slug, esc_html__('AI Chatbot', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI Chatbot', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_chatbot_panel', 'aiomatic_chatbot_panel');
                add_action( 'load-' . $chatbot, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $chatbot, 'aiomatic_load_playground' );
                add_action( 'load-' . $chatbot, 'aiomatic_load_live_preview' );
            }
            if($skip_assistant == false)
            {
                $assistants = add_submenu_page($base_slug, esc_html__('AI Assistants', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI Assistants', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_assistants_panel', 'aiomatic_assistants_panel');
                add_action( 'load-' . $assistants, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $assistants, 'aiomatic_load_playground' );
                add_action( 'load-' . $assistants, 'aiomatic_load_assistants' );
            }
            if($skip_forms == false)
            {
                $shortcodes = add_submenu_page($base_slug, esc_html__('AI Shortcodes & Forms', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI Shortcodes & Forms', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_shortcodes_panel', 'aiomatic_shortcodes_panel');
                add_action( 'load-' . $shortcodes, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $shortcodes, 'aiomatic_load_playground' );
                add_action( 'load-' . $shortcodes, 'aiomatic_load_forms' );
            }
            if($skip_embeddings == false)
            {
                $embeddings = add_submenu_page($base_slug, esc_html__('AI Embeddings', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI Embeddings', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_embeddings_panel', 'aiomatic_embeddings_panel');
                add_action( 'load-' . $embeddings, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $embeddings, 'aiomatic_load_playground' );
                add_action( 'load-' . $embeddings, 'aiomatic_load_embeddings' );
                add_action( 'load-' . $embeddings, 'aiomatic_load_auto_rules_css' );
            }
            if($skip_batch == false)
            {
                $batch = add_submenu_page($base_slug, esc_html__('AI Batch Requests', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI Batch Requests', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_batch_panel', 'aiomatic_batch_panel');
                add_action( 'load-' . $batch, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $batch, 'aiomatic_load_playground' );
                add_action( 'load-' . $batch, 'aiomatic_load_batch' );
            }
            if($skip_training == false)
            {
                $training = add_submenu_page($base_slug, esc_html__('AI Model Training', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI Model Training', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_openai_training', 'aiomatic_openai_training');
                add_action( 'load-' . $training, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $training, 'aiomatic_load_playground' );
                add_action( 'load-' . $training, 'aiomatic_load_training' );
            }
            if($skip_playground == false)
            {
                $playground = add_submenu_page($base_slug, esc_html__('AI Playground', 'aiomatic-automatic-ai-content-writer'), esc_html__('AI Playground', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_playground_panel', 'aiomatic_playground_panel');
                add_action( 'load-' . $playground, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $playground, 'aiomatic_load_playground' );
                add_action( 'load-' . $playground, 'aiomatic_load_prompt_database' );
            }
            if($skip_limits == false)
            {
                $openai_status = add_submenu_page($base_slug, esc_html__('Limits & Statistics', 'aiomatic-automatic-ai-content-writer'), esc_html__('Limits & Statistics', 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_openai_status', 'aiomatic_openai_status');
                add_action( 'load-' . $openai_status, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $openai_status, 'aiomatic_load_playground' );
                add_action( 'load-' . $openai_status, 'aiomatic_load_stats' );
            }
            if($skip_more == false)
            {
                $more = add_submenu_page($base_slug, esc_html__("More Features", 'aiomatic-automatic-ai-content-writer'), esc_html__("More Features", 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_more', 'aiomatic_more');
                add_action( 'load-' . $more, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $more, 'aiomatic_load_playground' );
            }
            if($skip_ext == false)
            {
                $ext = add_submenu_page($base_slug, esc_html__("Aiomatic Extensions", 'aiomatic-automatic-ai-content-writer'), esc_html__("Aiomatic Extensions", 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_extensions', 'aiomatic_extensions');
                add_action( 'load-' . $ext, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $ext, 'aiomatic_load_playground' );
                add_action( 'load-' . $ext, 'aiomatic_load_magic' );
            }
            if($skip_logs == false)
            {
                $logs = add_submenu_page($base_slug, esc_html__("Activity & Logging", 'aiomatic-automatic-ai-content-writer'), esc_html__("Activity & Logging", 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_logs', 'aiomatic_logs');
                add_action( 'load-' . $logs, 'aiomatic_load_all_admin_js' );
                add_action( 'load-' . $logs, 'aiomatic_load_playground' );
            }
            $media = add_media_page( 'Aiomatic Images', 'Aiomatic Images', 'access_aiomatic_menu', 'aiomatic-automatic-ai-content-writer', 'aiomatic_media_page' );
            add_action( 'load-' . $media, 'aiomatic_load_all_admin_js' );
        }
    }
    else
    {
        require(dirname(__FILE__) . "/res/aiomatic-activation.php");
        $base_slug = 'aiomatic_admin_settings';
        add_menu_page('Aiomatic AI Content Writer, Editor & Chatbot', 'Aiomatic', 'access_aiomatic_menu', $base_slug, $base_slug, plugins_url('images/icon.png', __FILE__));
        $main = add_submenu_page('aiomatic_admin_settings', esc_html__("Activation", 'aiomatic-automatic-ai-content-writer'), esc_html__("Activation", 'aiomatic-automatic-ai-content-writer'), 'access_aiomatic_menu', 'aiomatic_admin_settings');
        add_action( 'load-' . $main, 'aiomatic_load_all_admin_js' );
        add_action( 'load-' . $main, 'aiomatic_load_main_admin_js' );
        add_action( 'load-' . $main, 'aiomatic_load_activation' );
    }
}
function aiomatic_bulk_creators() 
{
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'bulk';
    ?>
    <div class="wrap">
        <div class="aiomatic-page-navigation-merged vertical left clearfix">
            <div class="aiomatic-tabs-navigation-wrapper">
                <div class="wrap gs_popuptype_holder seo_pops">
                    <h2 class="cr_center"><?php echo esc_html__("Bulk AI Post Creators", 'aiomatic-automatic-ai-content-writer');?></h2>
                </div>
                <nav class="nav-tab-wrapper">
                    <a href="?page=aiomatic_bulk_creators&tab=bulk" class="nav-tab <?php echo $tab == 'bulk' ? 'aiomatic-nav-tab-active' : ''; ?>"><?php echo esc_html__("Keywords/Titles To Blog Posts", 'aiomatic-automatic-ai-content-writer'); ?></a>
                    <a href="?page=aiomatic_bulk_creators&tab=youtube" class="nav-tab <?php echo $tab == 'youtube' ? 'aiomatic-nav-tab-active' : ''; ?>"><?php echo esc_html__("YouTube To Blog Posts", 'aiomatic-automatic-ai-content-writer'); ?></a>
                    <a href="?page=aiomatic_bulk_creators&tab=listicle" class="nav-tab <?php echo $tab == 'listicle' ? 'aiomatic-nav-tab-active' : ''; ?>"><?php echo esc_html__("Listicle Creator", 'aiomatic-automatic-ai-content-writer'); ?></a>
                    <a href="?page=aiomatic_bulk_creators&tab=amazon_roundup" class="nav-tab <?php echo $tab == 'amazon_roundup' ? 'aiomatic-nav-tab-active' : ''; ?>"><?php echo esc_html__("Amazon Product Roundup", 'aiomatic-automatic-ai-content-writer'); ?></a>
                    <a href="?page=aiomatic_bulk_creators&tab=amazon_review" class="nav-tab <?php echo $tab == 'amazon_review' ? 'aiomatic-nav-tab-active' : ''; ?>"><?php echo esc_html__("Amazon Product Review", 'aiomatic-automatic-ai-content-writer'); ?></a>
                    <a href="?page=aiomatic_bulk_creators&tab=csv" class="nav-tab <?php echo $tab == 'csv' ? 'aiomatic-nav-tab-active' : ''; ?>"><?php echo esc_html__("CSV AI Post Creator", 'aiomatic-automatic-ai-content-writer'); ?></a>
                </nav>
            </div>
        </div>
        <br/>
        <br/>
        <div>
        <?php
        switch ($tab) {
            case 'bulk':
                aiomatic_items_panel();
                break;
            case 'youtube':
                aiomatic_youtube_panel();
                break;
            case 'listicle':
                aiomatic_listicle_panel();
                break;
            case 'amazon_roundup':
                aiomatic_amazon_panel();
                break;
            case 'amazon_review':
                aiomatic_review_panel();
                break;
            case 'csv':
                aiomatic_csv_panel();
                break;
            default:
                echo esc_html__("Tab not found!", 'aiomatic-automatic-ai-content-writer');
        }
        ?>
        </div>
    </div>
    <?php
}
function aiomatic_load_post_admin_js()
{
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_post_files');
}

function aiomatic_admin_load_post_files()
{
    $name = md5(get_bloginfo());
    wp_register_script($name . '-submitter-script', plugins_url('scripts/poster.js', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-submitter-script');
    wp_localize_script($name . '-submitter-script', 'aiomatic_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-ajax-nonce'),
        'modelsvision' => aiomatic_get_all_vision_models()
	));
}
function aiomatic_load_auto_rules_css(){
    add_action('admin_enqueue_scripts', 'aiomatic_enqueue_only_rules');
}
function aiomatic_load_spin(){
    add_action('admin_enqueue_scripts', 'aiomatic_enqueue_only_spin');
}
function aiomatic_enqueue_only_spin()
{
    $name = md5(get_bloginfo());
    wp_enqueue_script($name . '-spin-script', plugins_url('scripts/spin.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
}
function aiomatic_enqueue_only_rules()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $more_logs = '0';
    if (isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on') 
    {
        $more_logs = '1';
    }
    $name = md5(get_bloginfo());
    wp_register_style($name . '-rules-style', plugins_url('styles/aiomatic-rules.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-rules-style');
    wp_enqueue_script($name . '-bulk-script', plugins_url('scripts/bulk-editor.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
    $footer_conf_settings = array(
        'plugin_dir_url' => plugin_dir_url(__FILE__),
		'nonce' => wp_create_nonce('openai-bulk-nonce'),
        'more_logs' => $more_logs,
		'ajaxurl' => admin_url('admin-ajax.php')
    );
    wp_localize_script($name . '-bulk-script', 'mybulksettings', $footer_conf_settings);
}
function aiomatic_load_admin_js(){
    add_action('admin_enqueue_scripts', 'aiomatic_enqueue_admin_js');
}
function aiomatic_enqueue_admin_js()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $more_logs = '0';
    if (isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on') 
    {
        $more_logs = '1';
    }
    $name = md5(get_bloginfo());
    wp_enqueue_script($name . '-modeselect-script', plugins_url('scripts/modeselect.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
    $footer_conf_settingsx = array(
        'showme' => esc_html__("Show Tutorial Video", 'aiomatic-automatic-ai-content-writer'),
        'hideme' => esc_html__("Hide Tutorial Video", 'aiomatic-automatic-ai-content-writer')
    );
    wp_localize_script($name . '-modeselect-script', 'varsx', $footer_conf_settingsx);
    wp_enqueue_script($name . '-footer-script', plugins_url('scripts/footer.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
    $cr_miv = ini_get('max_input_vars');
	if($cr_miv === null || $cr_miv === false || !is_numeric($cr_miv))
	{
        $cr_miv = '9999999';
    }
    $footer_conf_settings = array(
        'max_input_vars' => $cr_miv,
        'plugin_dir_url' => plugin_dir_url(__FILE__),
        'more_logs' => $more_logs,
        'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-run-nonce'),
		'bulk_nonce' => wp_create_nonce('openai-bulk-nonce')
    );
    wp_localize_script($name . '-footer-script', 'mycustomsettings', $footer_conf_settings);
    wp_register_style($name . '-rules-style', plugins_url('styles/aiomatic-rules.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-rules-style');
}
function aiomatic_load_main_admin_js(){
    add_action('admin_enqueue_scripts', 'aiomatic_enqueue_main_admin_js');
}
function aiomatic_load_activation(){
    add_action('admin_enqueue_scripts', 'aiomatic_enqueue_activation');
}

function aiomatic_enqueue_activation(){
    wp_register_style('aiomatic-activation-style', plugins_url('styles/aiomatic-activation.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style('aiomatic-activation-style');
}

function aiomatic_enqueue_main_admin_js(){
    $name = md5(get_bloginfo());
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    wp_enqueue_script($name . '-main-script', plugins_url('scripts/main.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    $plugin = plugin_basename(__FILE__);
    $plugin_slug = explode('/', $plugin);
    $plugin_slug = $plugin_slug[0];
    $footer_conf_settings = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'plugin_slug' => $plugin_slug
    );
    wp_localize_script($name . '-main-script', 'mycustomsettings', $footer_conf_settings);
    if(!isset($aiomatic_Main_Settings['best_user']))
    {
        $best_user = '';
    }
    else
    {
        $best_user = $aiomatic_Main_Settings['best_user'];
    }
    if(!isset($aiomatic_Main_Settings['best_password']))
    {
        $best_password = '';
    }
    else
    {
        $best_password = $aiomatic_Main_Settings['best_password'];
    }
    $header_main_settings = array(
        'best_user' => $best_user,
        'best_password' => $best_password,
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openai-ajax-nonce'),
    );
    wp_localize_script($name . '-main-script', 'mycustommainsettings', $header_main_settings);
}
function aiomatic_load_single()
{
    $name = md5(get_bloginfo());
    add_action('admin_enqueue_scripts', 'aiomatic_admin_single');
    wp_enqueue_script( 'jquery-ui-dialog' );
    wp_enqueue_style( 'wp-jquery-ui-dialog' );
    wp_enqueue_media();
    wp_enqueue_script( $name . '-media-loader-js', plugins_url( 'scripts/media.js' , __FILE__ ), array('jquery'), AIOMATIC_MAJOR_VERSION );
    wp_localize_script($name . '-media-loader-js', 'aiomatic_ajax_object', array(
		'nonce' => wp_create_nonce('openai-single-nonce')
	));
}
function aiomatic_load_omni()
{
    add_action('admin_enqueue_scripts', 'aiomatic_admin_omni');
}
function aiomatic_enqueue_custom_css_for_toc_meta() 
{
    if ( is_singular() ) 
    {
        global $post;
        $transient_key = 'aiomatic_toc_' . $post->ID;
        $meta_value = get_transient($transient_key);
        if ($meta_value == false) 
        {
            $meta_value = get_post_meta( $post->ID, 'aiomatic_toc', true );
            set_transient($transient_key, $meta_value, 12 * HOUR_IN_SECONDS);
        }
        if ( $meta_value === '1' ) 
        {
            $name = md5(get_bloginfo());
            wp_register_style($name . '-toc-css-ai', plugins_url('styles/toc.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
            wp_enqueue_style($name . '-toc-css-ai');
        }
    }
}
add_action( 'wp_enqueue_scripts', 'aiomatic_enqueue_custom_css_for_toc_meta' );
function aiomatic_admin_single()
{
    $name = md5(get_bloginfo());
    wp_register_script($name . '-single-script', plugins_url('scripts/single.js', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-single-script');
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['no_jobs']) && $aiomatic_Main_Settings['no_jobs'] === 'on')
    {
        $no_jobs = '1';
    }
    else
    {
        $no_jobs = '0';
    }
    wp_localize_script($name . '-single-script', 'aiomatic_ajax_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-single-nonce'),
        'no_jobs' => $no_jobs
	));
}
function aiomatic_admin_omni()
{
    $name = md5(get_bloginfo());
    wp_enqueue_script('jquery-ui-draggable');
    wp_enqueue_script('jquery-ui-sortable');
    wp_register_script($name . '-omni-script', plugins_url('scripts/automation.js', __FILE__), array('jquery', 'jquery-ui-sortable'), AIOMATIC_MAJOR_VERSION, false);
    wp_enqueue_script($name . '-omni-script');
    wp_localize_script($name . '-omni-script', 'aiomatic_ajax_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-omni-nonce'),
		'bulk_nonce' => wp_create_nonce('openai-bulk-nonce')
	));
    wp_register_style($name . '-automation', plugins_url('styles/automation.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-automation');
}
function aiomatic_load_all_admin_js(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_files');
}
function aiomatic_load_playground(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_playground');
}
function aiomatic_load_prompt_database(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_prompt_database');
}
function aiomatic_load_live_preview(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_live_preview');
}
function aiomatic_load_stats(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_stats');
}
function aiomatic_load_magic(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_magic');
}
function aiomatic_load_embeddings(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_embeddings');
}
function aiomatic_load_forms(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_forms');
    add_action('admin_footer', 'aiomatic_admin_footer');
}
function aiomatic_load_assistants(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_assistants');
    add_action('admin_footer', 'aiomatic_admin_footer');
}
function aiomatic_load_batch(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_batch');
    add_action('admin_footer', 'aiomatic_admin_footer');
}
function aiomatic_load_training(){
    add_action('admin_enqueue_scripts', 'aiomatic_admin_load_training');
    add_action('admin_footer', 'aiomatic_admin_footer');
}
add_filter("plugin_action_links_$plugin", 'aiomatic_add_rating_link');
function aiomatic_add_rating_link($links)
{
    $settings_link = '<a href="//codecanyon.net/downloads" target="_blank" title="Rate">
            <i class="wdi-rate-stars"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="#ffb900" stroke="#ffb900" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="#ffb900" stroke="#ffb900" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="#ffb900" stroke="#ffb900" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="#ffb900" stroke="#ffb900" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="#ffb900" stroke="#ffb900" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></i></a>';
    array_push($links, $settings_link);
    return $links;
}
add_filter("plugin_action_links_$plugin", 'aiomatic_add_support_link');
function aiomatic_add_support_link($links)
{
    $settings_link = '<a href="//coderevolution.ro/knowledge-base/" target="_blank">' . esc_html__('Support', 'aiomatic-automatic-ai-content-writer') . '</a>';
    array_push($links, $settings_link);
    return $links;
}
add_filter("plugin_action_links_$plugin", 'aiomatic_add_settings_link');
function aiomatic_add_settings_link($links)
{
    $settings_link = '<a href="admin.php?page=aiomatic_admin_settings">' . esc_html__('Settings', 'aiomatic-automatic-ai-content-writer') . '</a>';
    array_push($links, $settings_link);
    return $links;
}

function aiomatic_display_posts_off( $out, $pairs, $atts ) {
	$out['display_posts_off'] = apply_filters( 'display_posts_shortcode_inception_override', true );
	return $out;
}

add_filter('cron_schedules', 'aiomatic_add_cron_schedule');
function aiomatic_add_cron_schedule($schedules)
{
    $schedules['aiomatic_cron_ten'] = array(
        'interval' => 600,
        'display' => esc_html__('Aiomatic Cron 10 Minute', 'aiomatic-automatic-ai-content-writer')
    );
    $schedules['aiomatic_cron_sfert'] = array(
        'interval' => 900,
        'display' => esc_html__('Aiomatic Cron Quarter Hour', 'aiomatic-automatic-ai-content-writer')
    );
    $schedules['aiomatic_cron_half'] = array(
        'interval' => 1800,
        'display' => esc_html__('Aiomatic Cron Half Hour', 'aiomatic-automatic-ai-content-writer')
    );
    $schedules['aiomatic_cron'] = array(
        'interval' => 3600,
        'display' => esc_html__('Aiomatic Cron', 'aiomatic-automatic-ai-content-writer')
    );
    $schedules['minutely'] = array(
        'interval' => 60,
        'display' => esc_html__('Once A Minute', 'aiomatic-automatic-ai-content-writer')
    );
    $schedules['weekly']        = array(
        'interval' => 604800,
        'display' => esc_html__('Once Weekly', 'aiomatic-automatic-ai-content-writer')
    );
    $schedules['monthly']       = array(
        'interval' => 2592000,
        'display' => esc_html__('Once Monthly', 'aiomatic-automatic-ai-content-writer')
    );
    return $schedules;
}

register_deactivation_hook(__FILE__, 'aiomatic_my_deactivation');
function aiomatic_my_deactivation()
{
    wp_clear_scheduled_hook('aiomaticaction');
    wp_clear_scheduled_hook('aiomaticactionclear');
    $running = array();
    aiomatic_update_option('aiomatic_running_list', $running, false);
}
function aiomatic_enqueue_deactivation_modal($hook_suffix) 
{
    if ($hook_suffix !== 'plugins.php') 
    {
        return;
    }
    $plugin = plugin_basename(__FILE__);
    $plugin_slug = explode('/', $plugin);
    $plugin_slug = $plugin_slug[0];
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    wp_enqueue_script('aiomatic-deactivation', plugin_dir_url(__FILE__) . 'scripts/aiomatic-deactivation.js', array('jquery', 'jquery-ui-dialog'), AIOMATIC_MAJOR_VERSION, true);
    wp_localize_script('aiomatic-deactivation', 'aiomatic', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'plugin_slug' => $plugin_slug,
        'clear_data_nonce' => wp_create_nonce('aiomatic_clear_data_nonce'),
    ));
    wp_enqueue_style('aiomatic-deactivation-css', plugin_dir_url(__FILE__) . 'styles/aiomatic-deactivation.css');
}
add_action('admin_enqueue_scripts', 'aiomatic_enqueue_deactivation_modal');

add_action('aiomaticaction', 'aiomatic_cron');
add_action('aiomaticeditaction', 'aiomatic_do_bulk_post');
add_action('aiomaticactionclear', 'aiomatic_auto_clear_log');

add_action('add_meta_boxes', 'aiomatic_add_meta_box');
function aiomatic_add_meta_box()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] === 'on') 
    {
        if (!isset($aiomatic_Main_Settings['no_post_editor']) || $aiomatic_Main_Settings['no_post_editor'] !== 'on')
        {
            $name = md5(get_bloginfo());
            foreach ( get_post_types( '', 'names' ) as $post_type ) 
            {
                if(strstr($post_type, 'aiomatic_'))
                {
                    continue;
                }
                if(aiomatic_is_gutenberg_page())
                {
                    global $post;
                    wp_enqueue_script($name . '-poster-script', plugins_url('scripts/ai-post-creator-gutenberg.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
                    wp_localize_script($name . '-poster-script', 'aiomatic_creator_object', array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('openai-ajax-nonce'),
                        'generating_title' => esc_html__('Generating title...','aiomatic-automatic-ai-content-writer'),
                        'generating_meta' => esc_html__('Generating SEO meta description...','aiomatic-automatic-ai-content-writer'),
                        'generating_content' => esc_html__('Generating content...','aiomatic-automatic-ai-content-writer'),
                        'generating_excerpt' => esc_html__('Generating short description (excerpt)...','aiomatic-automatic-ai-content-writer'),
                        'generating_tags' => esc_html__('Generating tags...','aiomatic-automatic-ai-content-writer'),
                        'saving_post' => esc_html__('Saving post...','aiomatic-automatic-ai-content-writer'),
                        'generating_done' => esc_html__('Done!','aiomatic-automatic-ai-content-writer'),
                        'no_title' => esc_html__('Please enter a title idea/keyword','aiomatic-automatic-ai-content-writer') . ' ' . $post_type,
                        'no_change' => esc_html__('Nothing to save!','aiomatic-automatic-ai-content-writer') . ' ' . $post_type,
                        'no_step' => esc_html__('Please select at least one checkbox to generate!','aiomatic-automatic-ai-content-writer'),
                        'no_post_id' => esc_html__('An internal error was encountered, please try again later!','aiomatic-automatic-ai-content-writer'),
                        'error_occurred' => esc_html__('An error occurred, please try again later!','aiomatic-automatic-ai-content-writer'),
                        'post_id' => $post->ID
                    ));
                    add_meta_box(
                        'aiomatic_gutenberg_ai',
                        esc_html__('Aiomatic AI Content Writer', 'aiomatic-automatic-ai-content-writer'),
                        'aiomatic_gutenberg_metabox',
                        $post_type,
                        'advanced',
                        'default'
                    );
                }
                else
                {
                    wp_enqueue_script($name . '-poster-script', plugins_url('scripts/ai-post-creator.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
                    wp_localize_script($name . '-poster-script', 'aiomatic_creator_object', array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('openai-ajax-nonce'),
                        'generating_title' => esc_html__('Generating title...','aiomatic-automatic-ai-content-writer'),
                        'generating_meta' => esc_html__('Generating SEO meta description...','aiomatic-automatic-ai-content-writer'),
                        'generating_content' => esc_html__('Generating content...','aiomatic-automatic-ai-content-writer'),
                        'generating_excerpt' => esc_html__('Generating short description (excerpt)...','aiomatic-automatic-ai-content-writer'),
                        'generating_tags' => esc_html__('Generating tags...','aiomatic-automatic-ai-content-writer'),
                        'generating_done' => esc_html__('Done!','aiomatic-automatic-ai-content-writer'),
                        'no_title' => esc_html__('Please enter a title idea/keyword','aiomatic-automatic-ai-content-writer') . ' ' . $post_type,
                        'no_step' => esc_html__('Please select at least one checkbox to generate!','aiomatic-automatic-ai-content-writer'),
                        'error_occurred' => esc_html__('An error occurred, please try again later!','aiomatic-automatic-ai-content-writer')
                    ));
                    add_meta_box('aiomatic_meta_box_function_write_product', esc_html__('Aiomatic AI Content Writer', 'aiomatic-automatic-ai-content-writer'), 'aiomatic_meta_box_function_write_product', $post_type, 'advanced', 'default', array('__back_compat_meta_box' => true));
                    add_meta_box('aiomatic_meta_box_function_add', esc_html__('Aiomatic AI Content Editor', 'aiomatic-automatic-ai-content-writer'), 'aiomatic_meta_box_function', $post_type, 'advanced', 'default', array('__back_compat_meta_box' => true));
                }
            }
        }
    }
}
function aiomatic_add_csp_to_http_header() 
{
    $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
    if (isset($aiomatic_Chatbot_Settings['remote_chat']) && trim($aiomatic_Chatbot_Settings['remote_chat']) == 'on')
    {
        if (isset($aiomatic_Chatbot_Settings['allow_chatbot_site']) && trim($aiomatic_Chatbot_Settings['allow_chatbot_site']) != '') 
        {
            $allowed_domains = trim($aiomatic_Chatbot_Settings['allow_chatbot_site']);
            $allowed_domains = str_replace(',', ' ', $allowed_domains);
            $allowed_domains = preg_replace('/\s+/', ' ', $allowed_domains);
            $allowed_domains = "'self' " . $allowed_domains;
            header("Content-Security-Policy: frame-ancestors $allowed_domains;");
        }
    }
}
add_action('send_headers', 'aiomatic_add_csp_to_http_header');

function aiomatic_meta_box_function_write_product($post)
{
    require_once (dirname(__FILE__) . "/res/admin/ai-post.php");
}
function aiomatic_gutenberg_metabox($post)
{
    require_once (dirname(__FILE__) . "/res/admin/ai-post-gutenberg.php");
}
add_action('admin_enqueue_scripts', 'aiomatic_admin_do_post');
function aiomatic_admin_do_post()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $name = md5(get_bloginfo());
    if (!isset($aiomatic_Main_Settings['no_media_library']) || $aiomatic_Main_Settings['no_media_library'] !== 'on') 
    {
        global $post;
        wp_enqueue_media();
        wp_enqueue_script($name . '-media-tab', plugins_url('scripts/media-ai-script.js', __FILE__), array( 'jquery' ), AIOMATIC_MAJOR_VERSION, true);
        $no_stable = '0';
        if (!isset($aiomatic_Main_Settings['stability_app_id']) || trim($aiomatic_Main_Settings['stability_app_id']) == '') 
        {
            $no_stable = '1';
        }
        $no_midjourney = '0';
        if (!isset($aiomatic_Main_Settings['midjourney_app_id']) || trim($aiomatic_Main_Settings['midjourney_app_id']) == '') 
        {
            $no_midjourney = '1';
        }
        $no_replicate = '0';
        if (!isset($aiomatic_Main_Settings['replicate_app_id']) || trim($aiomatic_Main_Settings['replicate_app_id']) == '') 
        {
            $no_replicate = '1';
        }
        $royalty_free_sources = array();
        if(isset($aiomatic_Main_Settings['pixabay_api']) && $aiomatic_Main_Settings['pixabay_api'] != '')
        {
            $royalty_free_sources[] = 'pixabay';
        }
        if(isset($aiomatic_Main_Settings['flickr_api']) && $aiomatic_Main_Settings['flickr_api'] !== '')
        {
            $royalty_free_sources[] = 'flickr';
        }
        if(isset($aiomatic_Main_Settings['pexels_api']) && $aiomatic_Main_Settings['pexels_api'] !== '')
        {
            $royalty_free_sources[] = 'pexels';
        }
        if(isset($aiomatic_Main_Settings['pixabay_scrape']) && $aiomatic_Main_Settings['pixabay_scrape'] == 'on')
        {
            $royalty_free_sources[] = 'pixabayscrape';
        }
        if(isset($aiomatic_Main_Settings['unsplash_key']) && $aiomatic_Main_Settings['unsplash_key'] != '')
        {
            $royalty_free_sources[] = 'unsplash';
        }
        if(isset($aiomatic_Main_Settings['google_images']) && $aiomatic_Main_Settings['google_images'] == 'on')
        {
            $royalty_free_sources[] = 'google';
        }
        if(isset($aiomatic_Main_Settings['google_images_api']) && $aiomatic_Main_Settings['google_images_api'] == 'on')
        {
            $royalty_free_sources[] = 'googleapi';
        }
        $image_placeholder = plugins_url('images/loading.gif', __FILE__);
        wp_localize_script($name . '-media-tab', 'aiomatic_img_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('openai-ajax-nonce'),
            'image_placeholder' => $image_placeholder,
            'postId' => $post ? $post->ID : '',
            'no_stable' => $no_stable,
            'no_midjourney' => $no_midjourney,
            'no_replicate' => $no_replicate,
            'royalty_free_sources' => $royalty_free_sources
        ));
    }
    wp_register_style($name . '-media', plugins_url('styles/aiomatic-media.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-media');
    wp_enqueue_script($name . '-classic-poster-script', plugins_url('scripts/postnow.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
    wp_localize_script($name . '-classic-poster-script', 'aiomatic_poster_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openai-ajax-nonce')
    ));
    wp_enqueue_script($name . '-media-extender', plugins_url('scripts/media-extender.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION, true);
    wp_localize_script($name . '-media-extender', 'aiomatic_media_object', array(
        'nonce' => wp_create_nonce('openai-ajax-nonce')
    ));
}
function aiomatic_meta_box_function($post)
{
    $name = md5(get_bloginfo());
    wp_register_style($name . '-browser-style', plugins_url('styles/aiomatic-browser.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-browser-style');
    $metavalue = 'Post is not yet edited with Aiomatic.';
    $pid = get_the_ID();
    if($pid !== false) {
        $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
        if (isset($aiomatic_Spinner_Settings['custom_name']) && trim($aiomatic_Spinner_Settings['custom_name']) != '') {
            $custom_name = trim($aiomatic_Spinner_Settings['custom_name']);
            $custom_name = str_replace('%%current_date%%', date("Y-m-d"), $custom_name);
        } else {
            $custom_name = 'aiomatic_published';
        }
        $metavalue_check = get_post_meta($pid, $custom_name, true);
        if($metavalue_check == 'pub')
        {
            $metavalue = 'Post is edited with Aiomatic.';
        }
    }
    $ech = '<div class="bws_help_box bws_help_box_right dashicons dashicons-editor-help cr_align_middle"><div class="bws_hidden_help_text cr_min_260px disable_drag">' . esc_html__("The post will be edited respecting the template you select below, or the configurations you made in the 'AI Content Editor' plugin menu section.", 'aiomatic-automatic-ai-content-writer') . '</div></div>&nbsp;<span id="aiomatic_span">' . esc_html__("Manually Run AI Editing (AI Content Editor) For This Post", 'aiomatic-automatic-ai-content-writer') . ': </span><br/><br/><form id="aiomatic_form">';
    $ech .= '<b>Select AI Content Editor Template:</b><br/>';
    $ech .= '<select title="' . esc_html__('Select an AI Content Editor Template to be loaded.', 'aiomatic-automatic-ai-content-writer') . '" class="coderevolution_gutenberg_input cr_width_full editor_select_template" id="editor_select_template">';
    $temp_list = array();
    $args = array(
        'post_type' => 'aiomatic_editor_temp',
        'posts_per_page' => -1,
    );
    $the_query = new WP_Query( $args );
    if ( $the_query->have_posts() ) 
    {
        while ( $the_query->have_posts() ) 
        {
            $the_query->the_post();
            $temp_list[get_the_ID()] = get_the_title();
        }
    }
    wp_reset_postdata();
    if(!empty($temp_list))
    {
        $ech .= '<option value="">' . esc_html__("Use currently saved configuration", 'aiomatic-automatic-ai-content-writer') . '</option>';
        foreach($temp_list as $templid => $templ)
        {
            $ech .= '<option value="' . esc_attr($templid) . '">' . esc_html($templ) . '</option>';
        }
    }
    else
    {
        $ech .= '<option value="" disabled selected>' . esc_html__("No templates found (use currently saved configuration)", 'aiomatic-automatic-ai-content-writer') . '</option>';
    }
    $ech .= '</select><br/><br/>';
    $ech .= '<input class="button button-primary button-large" type="button" name="aiomatic_submit_post" id="aiomatic_submit_post" value="' . esc_html__('Process with Aiomatic', 'aiomatic-automatic-ai-content-writer') . '" onclick="aiomatic_post_now(' . $post->ID . ');"/>
    <hr/>
    <div class="bws_help_box bws_help_box_right dashicons dashicons-editor-help cr_align_middle"><div class="bws_hidden_help_text cr_min_260px disable_drag">' . esc_html__("Check if this post was already edited using Aiomatic. You can also toggle this post info, if you click the button from below.", 'aiomatic-automatic-ai-content-writer') . '</div></div>&nbsp;<span id="aiomatic_span">' . esc_html__("Post Editing Status", 'aiomatic-automatic-ai-content-writer') . ': </span>' . esc_html($metavalue) . '<br/><br/>
    <input class="button button-secondary button-large" type="button" name="aiomatic_toggle_post" id="aiomatic_toggle_post" value="' . esc_html__('Toggle Editing Status', 'aiomatic-automatic-ai-content-writer') . '" onclick="aiomatic_toggle_now(' . $post->ID . ');"/></form><br/><hr/>';
    echo $ech;
}
function aiomatic_cron_schedule()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] === 'on') 
    {
        $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
        if(!isset($aiomatic_Spinner_Settings['auto_run_interval']))
        {
            $aiomatic_Spinner_Settings['auto_run_interval'] = 'daily';
        }
        if(isset($aiomatic_Spinner_Settings['auto_edit']) && $aiomatic_Spinner_Settings['auto_edit'] == 'wp' && $aiomatic_Spinner_Settings['auto_run_interval'] != 'No')
        {
            if (!wp_next_scheduled('aiomaticeditaction')) 
            {
                wp_schedule_event(time(), $aiomatic_Spinner_Settings['auto_run_interval'], 'aiomaticeditaction');
            }
        }
        else
        {
            if (wp_next_scheduled('aiomaticeditaction')) 
            {
                wp_clear_scheduled_hook('aiomaticeditaction');
            }
        }
        if (!wp_next_scheduled('aiomaticaction')) {
            $unlocker = get_option('aiomatic_minute_running_unlocked', false);
            if($unlocker == '1')
            {
                $rez = wp_schedule_event(time(), 'minutely', 'aiomaticaction');
            }
            else
            {
                $rez = wp_schedule_event(time(), 'aiomatic_cron_sfert', 'aiomaticaction');
            }
            if ($rez === FALSE) {
                aiomatic_log_to_file('[Scheduler] Failed to schedule aiomaticaction to aiomatic_cron!');
            }
        }
        
        if (isset($aiomatic_Main_Settings['enable_logging']) && $aiomatic_Main_Settings['enable_logging'] === 'on' && isset($aiomatic_Main_Settings['auto_clear_logs']) && $aiomatic_Main_Settings['auto_clear_logs'] !== 'No') {
            if (!wp_next_scheduled('aiomaticactionclear')) {
                $rez = wp_schedule_event(time(), $aiomatic_Main_Settings['auto_clear_logs'], 'aiomaticactionclear');
                if ($rez === FALSE) {
                    aiomatic_log_to_file('[Scheduler] Failed to schedule aiomaticactionclear to ' . $aiomatic_Main_Settings['auto_clear_logs'] . '!');
                }
                add_option('aiomatic_schedule_time', $aiomatic_Main_Settings['auto_clear_logs'], '', false);
            } else {
                if (!get_option('aiomatic_schedule_time')) {
                    wp_clear_scheduled_hook('aiomaticactionclear');
                    $rez = wp_schedule_event(time(), $aiomatic_Main_Settings['auto_clear_logs'], 'aiomaticactionclear');
                    add_option('aiomatic_schedule_time', $aiomatic_Main_Settings['auto_clear_logs'], '', false);
                    if ($rez === FALSE) {
                        aiomatic_log_to_file('[Scheduler] Failed to schedule aiomaticactionclear to ' . $aiomatic_Main_Settings['auto_clear_logs'] . '!');
                    }
                } else {
                    $the_time = get_option('aiomatic_schedule_time');
                    if ($the_time != $aiomatic_Main_Settings['auto_clear_logs']) {
                        wp_clear_scheduled_hook('aiomaticactionclear');
                        delete_option('aiomatic_schedule_time');
                        $rez = wp_schedule_event(time(), $aiomatic_Main_Settings['auto_clear_logs'], 'aiomaticactionclear');
                        add_option('aiomatic_schedule_time', $aiomatic_Main_Settings['auto_clear_logs'], '', false);
                        if ($rez === FALSE) {
                            aiomatic_log_to_file('[Scheduler] Failed to schedule aiomaticactionclear to ' . $aiomatic_Main_Settings['auto_clear_logs'] . '!');
                        }
                    }
                }
            }
        } else {
            if (!wp_next_scheduled('aiomaticactionclear')) {
                delete_option('aiomatic_schedule_time');
            } else {
                wp_clear_scheduled_hook('aiomaticactionclear');
                delete_option('aiomatic_schedule_time');
            }
        }
    } else {
        if (wp_next_scheduled('aiomaticaction')) {
            wp_clear_scheduled_hook('aiomaticaction');
        }
        
        if (!wp_next_scheduled('aiomaticactionclear')) {
            delete_option('aiomatic_schedule_time');
        } else {
            wp_clear_scheduled_hook('aiomaticactionclear');
            delete_option('aiomatic_schedule_time');
        }
    }
}
function aiomatic_cron()
{
    $GLOBALS['wp_object_cache']->delete('aiomatic_rules_list', 'options');
    if (!get_option('aiomatic_rules_list')) {
        $rules = array();
    } else {
        $rules = get_option('aiomatic_rules_list');
    }
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['run_after']) && $aiomatic_Main_Settings['run_after'] != '' && isset($aiomatic_Main_Settings['run_before']) && $aiomatic_Main_Settings['run_before'] != '') 
    {
        $exit = true;
        $mytime = date("H:i");
        $min_time = $aiomatic_Main_Settings['run_after'];
        $max_time = $aiomatic_Main_Settings['run_before'];
        $date1 = DateTime::createFromFormat('H:i', $mytime);
        $date2 = DateTime::createFromFormat('H:i', $min_time);
        $date3 = DateTime::createFromFormat('H:i', $max_time);
        if ($date1 > $date2 && $date1 < $date3)
        {
            $exit = false;
        }
        if($exit == true)
        {
            return;
        }
    }
    $unlocker = get_option('aiomatic_minute_running_unlocked', false);
    if (!empty($rules)) {
        $cont = 0;
        foreach ($rules as $request => $bundle[]) {
            $bundle_values   = array_values($bundle);
            $myValues        = $bundle_values[$cont];
            $array_my_values = array_values($myValues);for($iji=0;$iji<count($array_my_values);++$iji){if(is_string($array_my_values[$iji])){$array_my_values[$iji]=stripslashes($array_my_values[$iji]);}}
            $schedule        = isset($array_my_values[0]) ? $array_my_values[0] : '24';
            $active          = isset($array_my_values[1]) ? $array_my_values[1] : '0';
            $last_run        = isset($array_my_values[2]) ? $array_my_values[2] : aiomatic_get_date_now();
            if ($active == '1') {
                $now                = aiomatic_get_date_now();
                if($unlocker == '1')
                {
                    $nextrun        = aiomatic_add_minute($last_run, $schedule);
                    $aiomatic_hour_diff = (int) aiomatic_minute_diff($now, $nextrun);
                }
                else
                {
                    $nextrun        = aiomatic_add_hour($last_run, $schedule);
                    $aiomatic_hour_diff = (int) aiomatic_hour_diff($now, $nextrun);
                }
                if ($aiomatic_hour_diff >= 0) {
                    aiomatic_run_rule($cont, 0, 1, 0, null, '', '');
                }
            }
            $cont = $cont + 1;
        }
    }
    $GLOBALS['wp_object_cache']->delete('aiomatic_youtube_list', 'options');
    if (!get_option('aiomatic_youtube_list')) {
        $rules2 = array();
    } else {
        $rules2 = get_option('aiomatic_youtube_list');
    }
    if (!empty($rules2)) {
        $cont2 = 0;
        foreach ($rules2 as $request2 => $bundle2[]) {
            $bundle_values2   = array_values($bundle2);
            $myValues2        = $bundle_values2[$cont2];
            $array_my_values2 = array_values($myValues2);for($iji=0;$iji<count($array_my_values2);++$iji){if(is_string($array_my_values2[$iji])){$array_my_values2[$iji]=stripslashes($array_my_values2[$iji]);}}
            $schedule2        = isset($array_my_values2[0]) ? $array_my_values2[0] : '24';
            $active2          = isset($array_my_values2[1]) ? $array_my_values2[1] : '0';
            $last_run2        = isset($array_my_values2[2]) ? $array_my_values2[2] : aiomatic_get_date_now();
            if ($active2 == '1') {
                $now2                = aiomatic_get_date_now();
                if($unlocker == '1')
                {
                    $nextrun2        = aiomatic_add_minute($last_run2, $schedule2);
                    $aiomatic_hour_diff2 = (int) aiomatic_minute_diff($now2, $nextrun2);
                }
                else
                {
                    $nextrun2        = aiomatic_add_hour($last_run2, $schedule2);
                    $aiomatic_hour_diff2 = (int) aiomatic_hour_diff($now2, $nextrun2);
                }
                if ($aiomatic_hour_diff2 >= 0) {
                    aiomatic_run_rule($cont2, 1, 1, 0, null, '', '');
                }
            }
            $cont2 = $cont2 + 1;
        }
    }
    $GLOBALS['wp_object_cache']->delete('aiomatic_amazon_list', 'options');
    if (!get_option('aiomatic_amazon_list')) {
        $rules3 = array();
    } else {
        $rules3 = get_option('aiomatic_amazon_list');
    }
    if (!empty($rules3)) {
        $cont3 = 0;
        foreach ($rules3 as $request3 => $bundle3[]) {
            $bundle_values3   = array_values($bundle3);
            $myValues3        = $bundle_values3[$cont3];
            $array_my_values3 = array_values($myValues3);for($iji=0;$iji<count($array_my_values3);++$iji){if(is_string($array_my_values3[$iji])){$array_my_values3[$iji]=stripslashes($array_my_values3[$iji]);}}
            $schedule3        = isset($array_my_values3[0]) ? $array_my_values3[0] : '24';
            $active3          = isset($array_my_values3[1]) ? $array_my_values3[1] : '0';
            $last_run3        = isset($array_my_values3[2]) ? $array_my_values3[2] : aiomatic_get_date_now();
            if ($active3 == '1') {
                $now3                = aiomatic_get_date_now();
                if($unlocker == '1')
                {
                    $nextrun3        = aiomatic_add_minute($last_run3, $schedule3);
                    $aiomatic_hour_diff3 = (int) aiomatic_minute_diff($now3, $nextrun3);
                }
                else
                {
                    $nextrun3        = aiomatic_add_hour($last_run3, $schedule3);
                    $aiomatic_hour_diff3 = (int) aiomatic_hour_diff($now3, $nextrun3);
                }
                if ($aiomatic_hour_diff3 >= 0) {
                    aiomatic_run_rule($cont3, 2, 1, 0, null, '', '');
                }
            }
            $cont3 = $cont3 + 1;
        }
    }
    $GLOBALS['wp_object_cache']->delete('aiomatic_review_list', 'options');
    if (!get_option('aiomatic_review_list')) {
        $rules4 = array();
    } else {
        $rules4 = get_option('aiomatic_review_list');
    }
    if (!empty($rules4)) {
        $cont4 = 0;
        foreach ($rules4 as $request4 => $bundle4[]) {
            $bundle_values4   = array_values($bundle4);
            $myValues4        = $bundle_values4[$cont4];
            $array_my_values4 = array_values($myValues4);for($iji=0;$iji<count($array_my_values4);++$iji){if(is_string($array_my_values4[$iji])){$array_my_values4[$iji]=stripslashes($array_my_values4[$iji]);}}
            $schedule4        = isset($array_my_values4[0]) ? $array_my_values4[0] : '24';
            $active4          = isset($array_my_values4[1]) ? $array_my_values4[1] : '0';
            $last_run4        = isset($array_my_values4[2]) ? $array_my_values4[2] : aiomatic_get_date_now();
            if ($active4 == '1') {
                $now4                = aiomatic_get_date_now();
                if($unlocker == '1')
                {
                    $nextrun4        = aiomatic_add_minute($last_run4, $schedule4);
                    $aiomatic_hour_diff4 = (int) aiomatic_minute_diff($now4, $nextrun4);
                }
                else
                {
                    $nextrun4        = aiomatic_add_hour($last_run4, $schedule4);
                    $aiomatic_hour_diff4 = (int) aiomatic_hour_diff($now4, $nextrun4);
                }
                if ($aiomatic_hour_diff4 >= 0) {
                    aiomatic_run_rule($cont4, 3, 1, 0, null, '', '');
                }
            }
            $cont4 = $cont4 + 1;
        }
    }
    $GLOBALS['wp_object_cache']->delete('aiomatic_csv_list', 'options');
    if (!get_option('aiomatic_csv_list')) {
        $rules5 = array();
    } else {
        $rules5 = get_option('aiomatic_csv_list');
    }
    if (!empty($rules5)) {
        $cont5 = 0;
        foreach ($rules5 as $request5 => $bundle5[]) {
            $bundle_values5   = array_values($bundle5);
            $myValues5        = $bundle_values5[$cont5];
            $array_my_values5 = array_values($myValues5);for($iji=0;$iji<count($array_my_values5);++$iji){if(is_string($array_my_values5[$iji])){$array_my_values5[$iji]=stripslashes($array_my_values5[$iji]);}}
            $schedule5        = isset($array_my_values5[0]) ? $array_my_values5[0] : '24';
            $active5          = isset($array_my_values5[1]) ? $array_my_values5[1] : '0';
            $last_run5        = isset($array_my_values5[2]) ? $array_my_values5[2] : aiomatic_get_date_now();
            if ($active5 == '1') {
                $now5                = aiomatic_get_date_now();
                if($unlocker == '1')
                {
                    $nextrun5        = aiomatic_add_minute($last_run5, $schedule5);
                    $aiomatic_hour_diff5 = (int) aiomatic_minute_diff($now5, $nextrun5);
                }
                else
                {
                    $nextrun5        = aiomatic_add_hour($last_run5, $schedule5);
                    $aiomatic_hour_diff5 = (int) aiomatic_hour_diff($now5, $nextrun5);
                }
                if ($aiomatic_hour_diff5 >= 0) {
                    aiomatic_run_rule($cont5, 4, 1, 0, null, '', '');
                }
            }
            $cont5 = $cont5 + 1;
        }
    }
    $GLOBALS['wp_object_cache']->delete('aiomatic_omni_list', 'options');
    if (!get_option('aiomatic_omni_list')) {
        $rules6 = array();
    } else {
        $rules6 = get_option('aiomatic_omni_list');
    }
    if (!empty($rules6)) {
        $cont6 = 0;
        foreach ($rules6 as $request6 => $bundle6[]) {
            $bundle_values6   = array_values($bundle6);
            $myValues6        = $bundle_values6[$cont6];
            $array_my_values6 = array_values($myValues6);for($iji=0;$iji<count($array_my_values6);++$iji){if(is_string($array_my_values6[$iji])){$array_my_values6[$iji]=stripslashes($array_my_values6[$iji]);}}
            $schedule6        = isset($array_my_values6[0]) ? $array_my_values6[0] : '24';
            $active6          = isset($array_my_values6[1]) ? $array_my_values6[1] : '0';
            $last_run6        = isset($array_my_values6[2]) ? $array_my_values6[2] : aiomatic_get_date_now();
            if ($active6 == '1') {
                $now6                = aiomatic_get_date_now();
                if($unlocker == '1')
                {
                    $nextrun6        = aiomatic_add_minute($last_run6, $schedule6);
                    $aiomatic_hour_diff6 = (int) aiomatic_minute_diff($now6, $nextrun6);
                }
                else
                {
                    $nextrun6       = aiomatic_add_hour($last_run6, $schedule6);
                    $aiomatic_hour_diff6 = (int) aiomatic_hour_diff($now6, $nextrun6);
                }
                if ($aiomatic_hour_diff6 >= 0) {
                    aiomatic_run_rule($cont6, 5, 1, 0, null, '', '');
                }
            }
            $cont6 = $cont6 + 1;
        }
    }
    $GLOBALS['wp_object_cache']->delete('aiomatic_listicle_list', 'options');
    if (!get_option('aiomatic_listicle_list')) {
        $rules7 = array();
    } else {
        $rules7 = get_option('aiomatic_listicle_list');
    }
    if (!empty($rules7)) {
        $cont7 = 0;
        foreach ($rules7 as $request7 => $bundle7[]) {
            $bundle_values7   = array_values($bundle7);
            $myValues7        = $bundle_values7[$cont7];
            $array_my_values7 = array_values($myValues7);for($iji=0;$iji<count($array_my_values7);++$iji){if(is_string($array_my_values7[$iji])){$array_my_values7[$iji]=stripslashes($array_my_values7[$iji]);}}
            $schedule7        = isset($array_my_values7[0]) ? $array_my_values7[0] : '24';
            $active7          = isset($array_my_values7[1]) ? $array_my_values7[1] : '0';
            $last_run7        = isset($array_my_values7[2]) ? $array_my_values7[2] : aiomatic_get_date_now();
            if ($active7 == '1') {
                $now7                = aiomatic_get_date_now();
                if($unlocker == '1')
                {
                    $nextrun7        = aiomatic_add_minute($last_run7, $schedule7);
                    $aiomatic_hour_diff7 = (int) aiomatic_minute_diff($now7, $nextrun7);
                }
                else
                {
                    $nextrun7       = aiomatic_add_hour($last_run7, $schedule7);
                    $aiomatic_hour_diff7 = (int) aiomatic_hour_diff($now7, $nextrun7);
                }
                if ($aiomatic_hour_diff7 >= 0) {
                    aiomatic_run_rule($cont7, 6, 1, 0, null, '', '');
                }
            }
            $cont7 = $cont7 + 1;
        }
    }
    $running = array();
    aiomatic_update_option('aiomatic_running_list', $running);
}
function aiomatic_extractKeyWords($string, $count = 10)
{
    $stopwords = array();
    $string = trim(preg_replace('/\s\s+/iu', '\s', strtolower($string)));
    $string = wp_strip_all_tags($string);
    $matchWords   = array_filter(explode(' ', $string), function($item) use ($stopwords)
    {
        return !($item == '' || in_array($item, $stopwords) || strlen($item) <= 2 || (function_exists('ctype_alnum') && ctype_alnum(trim(str_replace(' ', '', $item))) === FALSE) || is_numeric($item));
    });
    $wordCountArr = array_count_values($matchWords);
    arsort($wordCountArr);
    return array_keys(array_slice($wordCountArr, 0, $count));
}

function aiomatic_log_to_file($str)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['enable_logging']) && $aiomatic_Main_Settings['enable_logging'] == 'on') {
        $d = date("j-M-Y H:i:s e", current_time( 'timestamp' ));
        set_transient('aiomatic_log_history', $str, 60*60*12);
        if(function_exists('error_log'))
        {
            error_log("[$d] " . $str . "<br/>\r\n", 3, WP_CONTENT_DIR . '/aiomatic_info.log');
        }
    }
}
function aiomatic_delete_all_rules()
{
    aiomatic_update_option('aiomatic_rules_list', array());
    aiomatic_update_option('aiomatic_youtube_list', array());
    aiomatic_update_option('aiomatic_amazon_list', array());
    aiomatic_update_option('aiomatic_review_list', array());
    aiomatic_update_option('aiomatic_csv_list', array());
    aiomatic_update_option('aiomatic_omni_list', array());
    aiomatic_update_option('aiomatic_listicle_list', array());
}
function aiomatic_delete_all_posts()
{
    $failed                 = false;
    $number                 = 0;
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $post_list = array();
    $postsPerPage = 50000;
    $paged = 0;
    do
    {
        $postOffset = $paged * $postsPerPage;
        $query = array(
            'post_status' => array(
                'publish',
                'draft',
                'pending',
                'trash',
                'private',
                'future'
            ),
            'post_type' => array(
                'any'
            ),
            'numberposts' => $postsPerPage,
            'meta_key' => 'aiomatic_parent_rule',
            'fields' => 'ids',
            'offset'  => $postOffset
        );
        $got_me = get_posts($query);
        $post_list = array_merge($post_list, $got_me);
        $paged++;
    }while(!empty($got_me));
    wp_suspend_cache_addition(true);
    foreach ($post_list as $post) {
        $index = get_post_meta($post, 'aiomatic_parent_rule', true);
        if (isset($index) && $index !== '') {
            $args             = array(
                'post_parent' => $post
            );
            $post_attachments = get_children($args);
            if (isset($post_attachments) && !empty($post_attachments)) {
                foreach ($post_attachments as $attachment) {
                    wp_delete_attachment($attachment->ID, true);
                }
            }
            $res = wp_delete_post($post, true);
            if ($res === false) {
                $failed = true;
            } else {
                $number++;
            }
        }
    }
    wp_suspend_cache_addition(false);
    if ($failed === true) {
        if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) {
            aiomatic_log_to_file('[PostDelete] Failed to delete all posts!');
        }
    } else {
        if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) {
            aiomatic_log_to_file('[PostDelete] Successfuly deleted ' . esc_html($number) . ' posts!');
        }
    }
}
function aiomatic_replaceContentShortcodes($the_content, $img_attr, $rule_keywords)
{
    $matches = array();
    $i = 0;
    preg_match_all('~%regex\(\s*\"([^"]+?)\s*"\s*[,;]\s*\"([^"]*)\"\s*(?:[,;]\s*\"([^"]*?)\s*\")?(?:[,;]\s*\"([^"]*?)\s*\")?(?:[,;]\s*\"([^"]*?)\s*\")?\)%~si', $the_content, $matches);
    if (is_array($matches) && count($matches) && is_array($matches[0])) {
        for($i = 0; $i < count($matches[0]); $i++)
        {
            if (isset($matches[0][$i])) $fullmatch = $matches[0][$i];
            if (isset($matches[1][$i])) $search_in = aiomatic_replaceContentShortcodes($matches[1][$i], $img_attr, $rule_keywords);
            if (isset($matches[2][$i])) $matchpattern = $matches[2][$i];
            if (isset($matches[3][$i])) $element = $matches[3][$i];
            if (isset($matches[4][$i])) $delimeter = $matches[4][$i];if (isset($matches[5][$i])) $counter = $matches[5][$i];
            if (isset($matchpattern)) {
               if (preg_match('<^[\/#%+~[\]{}][\s\S]*[\/#%+~[\]{}]$>', $matchpattern, $z)) {
                  $ret = preg_match_all($matchpattern, $search_in, $submatches, PREG_PATTERN_ORDER);
               }
               else {
                  $ret = preg_match_all('~'.$matchpattern.'~si', $search_in, $submatches, PREG_PATTERN_ORDER);
               }
            }
            if (isset($submatches)) {
               if (is_array($submatches)) {
                  $empty_elements = array_keys($submatches[0], "");
                  foreach ($empty_elements as $e) {
                     unset($submatches[0][$e]);
                  }
                  $submatches[0] = array_unique($submatches[0]);
                  if (!is_numeric($element)) {
                     $element = 0;
                  }if (!is_numeric($counter)) {
                     $counter = 0;
                  }
                  if(isset($submatches[(int)($element)]))
                  {
                      $matched = $submatches[(int)($element)];
                  }
                  else
                  {
                      $matched = '';
                  }
                  $matched = array_unique((array)$matched);
                  if (empty($delimeter) || $delimeter == 'null') {
                     if (isset($matched[$counter])) $matched = $matched[$counter];
                  }
                  else {
                     $matched = implode($delimeter, $matched);
                  }
                  if (empty($matched)) {
                     $the_content = str_replace($fullmatch, '', $the_content);
                  } else {
                     $the_content = str_replace($fullmatch, $matched, $the_content);
                  }
               }
            }
        }
    }
    $pcxxx = explode('<!- template ->', $the_content);
    $the_content = $pcxxx[array_rand($pcxxx)];
    $the_content = str_replace('%%random_sentence%%', aiomatic_random_sentence_generator(), $the_content);
    $the_content = str_replace('%%random_sentence2%%', aiomatic_random_sentence_generator(false), $the_content); 
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['custom_html'])) {
        $the_content = str_replace('%%custom_html%%', $aiomatic_Main_Settings['custom_html'], $the_content);
    }
    if (isset($aiomatic_Main_Settings['custom_html2'])) {
        $the_content = str_replace('%%custom_html2%%', $aiomatic_Main_Settings['custom_html2'], $the_content);
    }
    $img_attr = str_replace('%%image_source_name%%', '', $img_attr);
    $img_attr = str_replace('%%image_source_url%%', '', $img_attr);
    $img_attr = str_replace('%%image_source_website%%', '', $img_attr);
    $the_content = str_replace('%%royalty_free_image_attribution%%', $img_attr, $the_content);
    $the_content = str_replace('%%keyword_search%%', $rule_keywords, $the_content);   
    $the_content = aiomatic_replaceSynergyShortcodes($the_content);
    $the_content = apply_filters('aiomatic_replace_aicontent_shortcode', $the_content);
    preg_match_all('#%%related_questions_([^%]*?)%%#i', $the_content, $mxatches);
    if(isset($mxatches[1][0]))
    {
        foreach($mxatches[1] as $googlematch)
        {
            $mtchres = aiomatic_scrape_related_questions($googlematch, 5, '', 1, 1, 0, 0, 2000, '', '');
            if(is_array($mtchres) && !empty($mtchres))
            {
                $quests = array();
                foreach($mtchres as $mra)
                {
                    $quests[] = $mra['q'];
                }
                $mtchres = implode(',', $quests);
            }
            $the_content = str_ireplace('%%related_questions_' . $googlematch . '%%', $mtchres, $the_content);
        }
    }
    return $the_content;
}

function aiomatic_try_new_key($app_id, $token)
{
    $appids = preg_split('/\r\n|\r|\n/', trim($app_id));
    $appids = array_map('trim', $appids);
    $appids = array_filter($appids);
    if(count($appids) > 1)
    {
        if (($key = array_search($token, $appids)) !== false) 
        {
            unset($appids[$key]);
        }
        if(count($appids) > 0)
        {
            $token_new = $appids[array_rand($appids)];
            if(!empty($token_new))
            {
                $token = $token_new;
            }
        }
    }
    $token = apply_filters('aiomatic_openai_api_key', $token);
    return $token;
}
function aiomatic_replaceTitleShortcodes($the_content)
{
    $matches = array();
    $i = 0;
    preg_match_all('~%regex\(\s*\"([^"]+?)\s*"\s*[,;]\s*\"([^"]*)\"\s*(?:[,;]\s*\"([^"]*?)\s*\")?(?:[,;]\s*\"([^"]*?)\s*\")?(?:[,;]\s*\"([^"]*?)\s*\")?\)%~si', $the_content, $matches);
    if (is_array($matches) && count($matches) && is_array($matches[0])) {
        for($i = 0; $i < count($matches[0]); $i++)
        {
            if (isset($matches[0][$i])) $fullmatch = $matches[0][$i];
            if (isset($matches[1][$i])) $search_in = aiomatic_replaceTitleShortcodes($matches[1][$i]);
            if (isset($matches[2][$i])) $matchpattern = $matches[2][$i];
            if (isset($matches[3][$i])) $element = $matches[3][$i];
            if (isset($matches[4][$i])) $delimeter = $matches[4][$i];if (isset($matches[5][$i])) $counter = $matches[5][$i];
            if (isset($matchpattern)) {
               if (preg_match('<^[\/#%+~[\]{}][\s\S]*[\/#%+~[\]{}]$>', $matchpattern, $z)) {
                  $ret = preg_match_all($matchpattern, $search_in, $submatches, PREG_PATTERN_ORDER);
               }
               else {
                  $ret = preg_match_all('~'.$matchpattern.'~si', $search_in, $submatches, PREG_PATTERN_ORDER);
               }
            }
            if (isset($submatches)) {
               if (is_array($submatches)) {
                  $empty_elements = array_keys($submatches[0], "");
                  foreach ($empty_elements as $e) {
                     unset($submatches[0][$e]);
                  }
                  $submatches[0] = array_unique($submatches[0]);
                  if (!is_numeric($element)) {
                     $element = 0;
                  }if (!is_numeric($counter)) {
                     $counter = 0;
                  }
                  if(isset($submatches[(int)($element)]))
                  {
                      $matched = $submatches[(int)($element)];
                  }
                  else
                  {
                      $matched = '';
                  }
                  $matched = array_unique((array)$matched);
                  if (empty($delimeter) || $delimeter == 'null') {
                     if (isset($matched[$counter])) $matched = $matched[$counter];
                  }
                  else {
                     $matched = implode($delimeter, $matched);
                  }
                  if (empty($matched)) {
                     $the_content = str_replace($fullmatch, '', $the_content);
                  } else {
                     $the_content = str_replace($fullmatch, $matched, $the_content);
                  }
               }
            }
        }
    }
    $pcxxx = explode('<!- template ->', $the_content);
    $the_content = $pcxxx[array_rand($pcxxx)];
    $the_content = str_replace('%%random_sentence%%', aiomatic_random_sentence_generator(), $the_content);
    $the_content = str_replace('%%random_sentence2%%', aiomatic_random_sentence_generator(false), $the_content);
    $the_content = aiomatic_replaceSynergyShortcodes($the_content);
    $the_content = apply_filters('aiomatic_replace_aicontent_shortcode', $the_content);
    preg_match_all('#%%related_questions_([^%]*?)%%#i', $the_content, $mxatches);
    if(isset($mxatches[1][0]))
    {
        foreach($mxatches[1] as $googlematch)
        {
            $mtchres = aiomatic_scrape_related_questions($googlematch, 5, '', 1, 1, 0, 0, 2000, '', '');
            if(is_array($mtchres) && !empty($mtchres))
            {
                $quests = array();
                foreach($mtchres as $mra)
                {
                    $quests[] = $mra['q'];
                }
                $mtchres = implode(',', $quests);
            }
            $the_content = str_ireplace('%%related_questions_' . $googlematch . '%%', $mtchres, $the_content);
        }
    }
    return $the_content;
}

function aiomatic_clearFromList($param, $type)
{
    $GLOBALS['wp_object_cache']->delete('aiomatic_running_list', 'options');
    $running = get_option('aiomatic_running_list');
    if($running !== false)
    {
        $key     = array_search(array(
            $param => $type
        ), $running);
        if ($key !== FALSE) {
            unset($running[$key]);
            aiomatic_update_option('aiomatic_running_list', $running);
        }
    }
}

function aiomatic_generate_title($content)
{
    $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
    $content        = preg_replace($regexEmoticons, '', $content);
    $regexSymbols   = '/[\x{1F300}-\x{1F5FF}]/u';
    $content        = preg_replace($regexSymbols, '', $content);
    $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
    $content        = preg_replace($regexTransport, '', $content);
    $regexMisc      = '/[\x{2600}-\x{26FF}]/u';
    $content        = preg_replace($regexMisc, '', $content);
    $regexDingbats  = '/[\x{2700}-\x{27BF}]/u';
    $content        = preg_replace($regexDingbats, '', $content);
    $pattern        = "/[a-zA-Z]*[:\/\/]*[A-Za-z0-9\-_]+\.+[A-Za-z0-9\.\/%&=\?\-_]+/i";
    $replacement    = "";
    $content        = preg_replace($pattern, $replacement, $content);
    $return         = trim(trim(trim(wp_trim_words($content, 14)), '.'), ',');
    return $return;
}
function aiomatic_replaceSynergyShortcodes($the_content)
{
    $regex = '#%%([a-z0-9]+?)(?:_title)?_(\d+?)_(\d+?)%%#';
    $rezz = preg_match_all($regex, $the_content, $matches);
    if ($rezz === FALSE) {
        return $the_content;
    }
    if(isset($matches[1][0]))
    {
        $two_var_functions = array('pdfomatic');
        $three_var_functions = array('bhomatic', 'crawlomatic', 'dmomatic', 'ezinomatic', 'fbomatic', 'flickomatic', 'imguromatic', 'iui', 'instamatic', 'linkedinomatic', 'mediumomatic', 'pinterestomatic', 'echo', 'spinomatic', 'tumblomatic', 'wordpressomatic', 'wpcomomatic', 'youtubomatic', 'mastermind', 'businessomatic');
        $four_var_functions = array('aiomatic', 'contentomatic', 'quoramatic', 'newsomatic', 'aliomatic', 'amazomatic', 'blogspotomatic', 'bookomatic', 'careeromatic', 'cbomatic', 'cjomatic', 'craigomatic', 'ebayomatic', 'etsyomatic', 'rakutenomatic', 'learnomatic', 'eventomatic', 'gameomatic', 'gearomatic', 'giphyomatic', 'gplusomatic', 'hackeromatic', 'imageomatic', 'midas', 'movieomatic', 'nasaomatic', 'ocartomatic', 'okomatic', 'playomatic', 'recipeomatic', 'redditomatic', 'soundomatic', 'mp3omatic', 'ticketomatic', 'tmomatic', 'trendomatic', 'tuneomatic', 'twitchomatic', 'twitomatic', 'vimeomatic', 'viralomatic', 'vkomatic', 'walmartomatic', 'bestbuyomatic', 'wikiomatic', 'xlsxomatic', 'yelpomatic', 'yummomatic');
        for ($i = 0; $i < count($matches[1]); $i++)
        {
            $replace_me = false;
            if(in_array($matches[1][$i], $four_var_functions))
            {
                $za_function = $matches[1][$i] . '_run_rule';
                if(function_exists($za_function))
                {
                    $xreflection = new ReflectionFunction($za_function);
                    if($xreflection->getNumberOfParameters() >= 4)
                    {  
                        $rule_runner = $za_function($matches[3][$i], $matches[2][$i], 0, 1);
                        if($rule_runner != 'fail' && $rule_runner != 'nochange' && $rule_runner != 'ok' && $rule_runner !== false)
                        {
                            if(is_array($rule_runner))
                            {
                                $the_content = str_replace('%%' . $matches[1][$i] . '_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner[0], $the_content);
                                $the_content = str_replace('%%' . $matches[1][$i] . '_title_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner[1], $the_content);
                            }
                            else
                            {
                                $the_content = str_replace('%%' . $matches[1][$i] . '_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner, $the_content);
                                $the_content = str_replace('%%' . $matches[1][$i] . '_title_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', '', $the_content);
                            }
                            $replace_me = true;
                        }
                    }
                    $xreflection = null;
                    unset($xreflection);
                }
            }
            elseif(in_array($matches[1][$i], $three_var_functions))
            {
                $za_function = $matches[1][$i] . '_run_rule';
                if(function_exists($za_function))
                {
                    $xreflection = new ReflectionFunction($za_function);
                    if($xreflection->getNumberOfParameters() >= 3)
                    {
                        $rule_runner = $za_function($matches[3][$i], 0, 1);
                        if($rule_runner != 'fail' && $rule_runner != 'nochange' && $rule_runner != 'ok' && $rule_runner !== false)
                        {
                            if(is_array($rule_runner))
                            {
                                $the_content = str_replace('%%' . $matches[1][$i] . '_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner[0], $the_content);
                                $the_content = str_replace('%%' . $matches[1][$i] . '_title_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner[1], $the_content);
                            }
                            else
                            {
                                $the_content = str_replace('%%' . $matches[1][$i] . '_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner, $the_content);
                                $the_content = str_replace('%%' . $matches[1][$i] . '_title_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', '', $the_content);
                            }
                            $replace_me = true;
                        }
                    }
                    $xreflection = null;
                    unset($xreflection);
                }
            }
            elseif(in_array($matches[1][$i], $two_var_functions))
            {
                $za_function = $matches[1][$i] . '_run_rule';
                if(function_exists($za_function))
                {
                    $xreflection = new ReflectionFunction($za_function);
                    if($xreflection->getNumberOfParameters() >= 2)
                    {
                        $rule_runner = $za_function($matches[3][$i], 1);
                        if($rule_runner != 'fail' && $rule_runner != 'nochange' && $rule_runner != 'ok' && $rule_runner !== false)
                        {
                            if(is_array($rule_runner))
                            {
                                $the_content = str_replace('%%' . $matches[1][$i] . '_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner[0], $the_content);
                                $the_content = str_replace('%%' . $matches[1][$i] . '_title_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner[1], $the_content);
                            }
                            else
                            {
                                $the_content = str_replace('%%' . $matches[1][$i] . '_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', $rule_runner, $the_content);
                                $the_content = str_replace('%%' . $matches[1][$i] . '_title_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', '', $the_content);
                            }
                            $replace_me = true;
                        }
                    }
                    $xreflection = null;
                    unset($xreflection);
                }
            }
            if($replace_me == false)
            {
                $the_content = str_replace('%%' . $matches[1][$i] . '_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', '', $the_content);
                $the_content = str_replace('%%' . $matches[1][$i] . '_title_' . $matches[2][$i] . '_' . $matches[3][$i] . '%%', '', $the_content);
            }
        }
    }
    $spintax = new Aiomatic_Spintax();
    $the_content = $spintax->Parse($the_content);
    return $the_content;
}
class Aiomatic_keywords{ 
    public static $charset = 'UTF-8';
    public static $banned_words = array('adsbygoogle', 'able', 'about', 'above', 'act', 'add', 'afraid', 'after', 'again', 'against', 'age', 'ago', 'agree', 'all', 'almost', 'alone', 'along', 'already', 'also', 'although', 'always', 'am', 'amount', 'an', 'and', 'anger', 'angry', 'animal', 'another', 'answer', 'any', 'appear', 'apple', 'are', 'arrive', 'arm', 'arms', 'around', 'arrive', 'as', 'ask', 'at', 'attempt', 'aunt', 'away', 'back', 'bad', 'bag', 'bay', 'be', 'became', 'because', 'become', 'been', 'before', 'began', 'begin', 'behind', 'being', 'bell', 'belong', 'below', 'beside', 'best', 'better', 'between', 'beyond', 'big', 'body', 'bone', 'born', 'borrow', 'both', 'bottom', 'box', 'boy', 'break', 'bring', 'brought', 'bug', 'built', 'busy', 'but', 'buy', 'by', 'call', 'came', 'can', 'cause', 'choose', 'close', 'close', 'consider', 'come', 'consider', 'considerable', 'contain', 'continue', 'could', 'cry', 'cut', 'dare', 'dark', 'deal', 'dear', 'decide', 'deep', 'did', 'die', 'do', 'does', 'dog', 'done', 'doubt', 'down', 'during', 'each', 'ear', 'early', 'eat', 'effort', 'either', 'else', 'end', 'enjoy', 'enough', 'enter', 'even', 'ever', 'every', 'except', 'expect', 'explain', 'fail', 'fall', 'far', 'fat', 'favor', 'fear', 'feel', 'feet', 'fell', 'felt', 'few', 'fill', 'find', 'fit', 'fly', 'follow', 'for', 'forever', 'forget', 'from', 'front', 'gave', 'get', 'gives', 'goes', 'gone', 'good', 'got', 'gray', 'great', 'green', 'grew', 'grow', 'guess', 'had', 'half', 'hang', 'happen', 'has', 'hat', 'have', 'he', 'hear', 'heard', 'held', 'hello', 'help', 'her', 'here', 'hers', 'high', 'hill', 'him', 'his', 'hit', 'hold', 'hot', 'how', 'however', 'I', 'if', 'ill', 'in', 'indeed', 'instead', 'into', 'iron', 'is', 'it', 'its', 'just', 'keep', 'kept', 'knew', 'know', 'known', 'late', 'least', 'led', 'left', 'lend', 'less', 'let', 'like', 'likely', 'likr', 'lone', 'long', 'look', 'lot', 'make', 'many', 'may', 'me', 'mean', 'met', 'might', 'mile', 'mine', 'moon', 'more', 'most', 'move', 'much', 'must', 'my', 'near', 'nearly', 'necessary', 'neither', 'never', 'next', 'no', 'none', 'nor', 'not', 'note', 'nothing', 'now', 'number', 'of', 'off', 'often', 'oh', 'on', 'once', 'only', 'or', 'other', 'ought', 'our', 'out', 'please', 'prepare', 'probable', 'pull', 'pure', 'push', 'put', 'raise', 'ran', 'rather', 'reach', 'realize', 'reply', 'require', 'rest', 'run', 'said', 'same', 'sat', 'saw', 'say', 'see', 'seem', 'seen', 'self', 'sell', 'sent', 'separate', 'set', 'shall', 'she', 'should', 'side', 'sign', 'since', 'so', 'sold', 'some', 'soon', 'sorry', 'stay', 'step', 'stick', 'still', 'stood', 'such', 'sudden', 'suppose', 'take', 'taken', 'talk', 'tall', 'tell', 'ten', 'than', 'thank', 'that', 'the', 'their', 'them', 'then', 'there', 'therefore', 'these', 'they', 'this', 'those', 'though', 'through', 'till', 'to', 'today', 'told', 'tomorrow', 'too', 'took', 'tore', 'tought', 'toward', 'tried', 'tries', 'trust', 'try', 'turn', 'two', 'under', 'until', 'up', 'upon', 'us', 'use', 'usual', 'various', 'verb', 'very', 'visit', 'want', 'was', 'we', 'well', 'went', 'were', 'what', 'when', 'where', 'whether', 'which', 'while', 'white', 'who', 'whom', 'whose', 'why', 'will', 'with', 'within', 'without', 'would', 'yes', 'yet', 'you', 'young', 'your', 'br', 'img', 'p','lt', 'gt', 'quot', 'copy');
    public static $min_word_length = 4;
    
    public static function text($text, $length = 160)
    {
        return self::limit_chars(self::clean($text), $length,'',TRUE);
    } 

    public static function keywords($text, $max_keys = 3)
    {
        include (dirname(__FILE__) . "/res/diacritics.php");
        $wordcount = array_count_values(str_word_count(self::clean($text), 1, $diacritics));
        foreach ($wordcount as $key => $value) 
        {
            if ( (strlen($key)<= self::$min_word_length) OR in_array($key, self::$banned_words))
                unset($wordcount[$key]);
        }
        uasort($wordcount,[self::class, 'cmp']);
        $wordcount = array_slice($wordcount,0, $max_keys);
        return implode(' ', array_keys($wordcount));
    } 

    private static function clean($text)
    { 
        $text = html_entity_decode($text,ENT_QUOTES,self::$charset);
        $text = strip_tags($text);
        $text = preg_replace('/\s\s+/', ' ', $text);
        $text = str_replace (array('\r\n', '\n', '+'), ',', $text);
        return trim($text); 
    } 

    private static function cmp($a, $b) 
    {
        if ($a == $b) return 0; 

        return ($a < $b) ? 1 : -1; 
    } 

    private static function limit_chars($str, $limit = 100, $end_char = NULL, $preserve_words = FALSE)
    {
        $end_char = ($end_char === NULL) ? '&#8230;' : $end_char;
        $limit = (int) $limit;
        if (trim($str) === '' OR strlen($str) <= $limit)
            return $str;
        if ($limit <= 0)
            return $end_char;
        if ($preserve_words === FALSE)
            return rtrim(substr($str, 0, $limit)).$end_char;
        if ( ! preg_match('/^.{0,'.$limit.'}\s/us', $str, $matches))
            return $end_char;
        return rtrim($matches[0]).((strlen($matches[0]) === strlen($str)) ? '' : $end_char);
    }
}

function aiomatic_scrape_related_questions($query, $headings, $model, $temperature, $top_p, $presence_penalty, $frequency_penalty, $max_tokens, $headings_ai_command, $headings_assistant_id = '')
{
    $headings = intval($headings);
    $results = array();
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['valueserp_auth']) && trim($aiomatic_Main_Settings['valueserp_auth']) != '')
    {
        $serpapi = 'https://api.valueserp.com/search?q=' . urlencode($query) . '&api_key=' . trim($aiomatic_Main_Settings['valueserp_auth']);
        $html_data = aiomatic_get_web_page($serpapi);
        if ($html_data !== FALSE) 
        {
            $json = json_decode($html_data);
            if ($json !== null) 
            {
                if(isset($json->related_searches[0]->query))
                {
                    foreach($json->related_searches as $qq)
                    {
                        $answer = '';
                        if(isset($qq->answer))
                        {
                            $answer = $qq->answer;
                        }
                        $rec = array("q" => $qq->query, "a" => $answer, "l" => $qq->link);
                        if(!isset($results[$qq->query]))
                        {
                            $results[$qq->query] = $rec;
                        }
                        if(count($results) >= $headings)
                        {
                            break;
                        }
                    }
                    if(count($results) > 0 && count($results) < $headings)
                    {
                        $ok = true;
                        while($ok && count($results) < $headings)
                        {
                            $last_elem = end($results);
                            $serpapi = 'https://api.valueserp.com/search?q=' . urlencode($last_elem['q']) . '&api_key=' . trim($aiomatic_Main_Settings['valueserp_auth']);
                            $html_data = aiomatic_get_web_page($serpapi);
                            if ($html_data !== FALSE) 
                            {
                                $json = json_decode($html_data);
                                if ($json !== null) 
                                {
                                    if(isset($json->related_searches[0]->query))
                                    {
                                        $count_before = count($results);
                                        foreach($json->related_searches as $qq)
                                        {
                                            $answer = '';
                                            if(isset($qq->answer))
                                            {
                                                $answer = $qq->answer;
                                            }
                                            $rec = array("q" => $qq->query, "a" => $answer, "l" => $qq->link);
                                            if(!isset($results[$qq->query]))
                                            {
                                                $results[$qq->query] = $rec;
                                            }
                                            if(count($results) >= $headings)
                                            {
                                                break;
                                            }
                                        }
                                        $count_after = count($results);
                                        if($count_after == $count_before)
                                        {
                                            $ok = false;
                                        }
                                    }
                                    else
                                    {
                                        $ok = false;
                                    }
                                }
                                else
                                {
                                    $ok = false;
                                }
                            }
                            else
                            {
                                $ok = false;
                            }
                        }
                    }
                }
            }
        }
    }
    if (isset($aiomatic_Main_Settings['serpapi_auth']) && trim($aiomatic_Main_Settings['serpapi_auth']) != '')
    {
        if(count($results) < $headings)
        {
            $serpapi = 'https://serpapi.com/search.json?q=' . urlencode($query) . '&api_key=' . trim($aiomatic_Main_Settings['serpapi_auth']);
            $html_data = aiomatic_get_web_page($serpapi);
            if ($html_data !== FALSE) 
            {
                $json = json_decode($html_data);
                if ($json !== null) 
                {
                    if(isset($json->related_questions[0]->question))
                    {
                        foreach($json->related_questions as $qq)
                        {
                            $answer = '';
                            if(isset($qq->snippet))
                            {
                                $answer = $qq->snippet;
                            }
                            elseif(isset($qq->title))
                            {
                                $answer = $qq->title;
                                if(isset($qq->list))
                                {
                                    $answer .= ' ';
                                    foreach($qq->list as $ll)
                                    {
                                        $answer .= trim($ll, ' .') . ', ';
                                    }
                                    $answer = trim($answer, ' ,');
                                }
                            }
                            $rec = array("q" => $qq->question, "a" => $answer, "l" => $qq->link);
                            if(!isset($results[$qq->question]))
                            {
                                $results[$qq->question] = $rec;
                            }
                            if(count($results) >= $headings)
                            {
                                break;
                            }
                        }
                        if(count($results) > 0 && count($results) < $headings)
                        {
                            $ok = true;
                            while($ok && count($results) < $headings)
                            {
                                $last_elem = end($results);
                                $serpapi = 'https://serpapi.com/search.json?q=' . urlencode($last_elem['q']) . '&api_key=' . trim($aiomatic_Main_Settings['serpapi_auth']);
                                $html_data = aiomatic_get_web_page($serpapi);
                                if ($html_data !== FALSE) 
                                {
                                    $json = json_decode($html_data);
                                    if ($json !== null) 
                                    {
                                        if(isset($json->related_questions[0]->question))
                                        {
                                            $count_before = count($results);
                                            foreach($json->related_questions as $qq)
                                            {
                                                $answer = '';
                                                if(isset($qq->snippet))
                                                {
                                                    $answer = $qq->snippet;
                                                }
                                                elseif(isset($qq->title))
                                                {
                                                    $answer = $qq->title;
                                                    if(isset($qq->list))
                                                    {
                                                        $answer .= ' ';
                                                        foreach($qq->list as $ll)
                                                        {
                                                            $answer .= trim($ll, ' .') . ', ';
                                                        }
                                                        $answer = trim($answer, ' ,');
                                                    }
                                                }
                                                $rec = array("q" => $qq->question, "a" => $answer, "l" => $qq->link);
                                                if(!isset($results[$qq->question]))
                                                {
                                                    $results[$qq->question] = $rec;
                                                }
                                                if(count($results) >= $headings)
                                                {
                                                    break;
                                                }
                                            }
                                            $count_after = count($results);
                                            if($count_after == $count_before)
                                            {
                                                $ok = false;
                                            }
                                        }
                                        else
                                        {
                                            $ok = false;
                                        }
                                    }
                                    else
                                    {
                                        $ok = false;
                                    }
                                }
                                else
                                {
                                    $ok = false;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if (isset($aiomatic_Main_Settings['bing_auth_internet']) && trim($aiomatic_Main_Settings['bing_auth_internet']) != '')
    {
        if(count($results) < $headings)
        {
            $kkey = trim($aiomatic_Main_Settings['bing_auth_internet']);
            $curl = curl_init();
            $queryUrl = "https://api.bing.microsoft.com/v7.0/search?q=" . urlencode($query);
            curl_setopt_array($curl, [
                CURLOPT_URL => $queryUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => [
                    "Ocp-Apim-Subscription-Key: $kkey"
                ]
            ]);
            $html_data = curl_exec($curl);
            curl_close($curl);
            if ($html_data !== FALSE) 
            {
                $json = json_decode($html_data);
                if ($json !== null && isset($json->relatedSearches->value)) 
                {
                    foreach ($json->relatedSearches->value as $jsx) 
                    {
                        if (isset($jsx->text)) 
                        {
                            if(!isset($results[$jsx->text]))
                            {
                                $results[$jsx->text] = '';
                            }
                            if(count($results) >= $headings)
                            {
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
    if (isset($aiomatic_Main_Settings['serper_auth']) && trim($aiomatic_Main_Settings['serper_auth']) != '')
    {
        if(count($results) < $headings)
        {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://google.serper.dev/search',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>'{"q":"' . str_replace('"', "'", $query) . '"}',
                CURLOPT_HTTPHEADER => array(
                    'X-API-KEY: ' . trim($aiomatic_Main_Settings['serper_auth']),
                    'Content-Type: application/json'
                ),
            ));
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $html_data = curl_exec($curl);
            if ($html_data !== FALSE) 
            {
                $json = json_decode($html_data);
                if ($json !== null) 
                {
                    if(isset($json->peopleAlsoAsk))
                    {
                        foreach($json->peopleAlsoAsk as $jsx)
                        {
                            if(isset($jsx->question))
                            {
                                if(!isset($results[$jsx->question]))
                                {
                                    $results[$jsx->question] = $jsx->snippet;
                                }
                                if(count($results) >= $headings)
                                {
                                    break;
                                }
                            }
                        }
                    }
                    if(count($results) < $headings)
                    {
                        if(isset($json->relatedSearches))
                        {
                            foreach($json->relatedSearches as $jsx)
                            {
                                if(isset($jsx->query))
                                {
                                    if(!isset($results[$jsx->query]))
                                    {
                                        $results[$jsx->query] = '';
                                    }
                                    if(count($results) >= $headings)
                                    {
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if (!isset($aiomatic_Main_Settings['bing_off']) || trim($aiomatic_Main_Settings['bing_off']) != 'on')
    {
        if(count($results) < $headings)
        {
            require_once (dirname(__FILE__) . "/res/simple_html_dom.php");
            $url = "https://www.bing.com/search?q=" . urlencode($query);
            $related_expre = 'div[data-tag="RelatedQnA.Item"]';
            $html_data = aiomatic_get_web_page($url);
            if ($html_data !== FALSE) 
            {
                $html_dom_original_html = aiomatic_str_get_html($html_data);
                if($html_dom_original_html !== false && method_exists($html_dom_original_html, 'find'))
                {
                    $ret = $html_dom_original_html->find( trim($related_expre) );
                    foreach ($ret as $element ) 
                    {
                        $q = $element->find("div",0);
                        if($q !== null)
                        {
                            $q = $q->children(0);
                            if($q !== null)
                            {
                                $q = $q->children(0);
                                if($q !== null)
                                {
                                    $q = $q->children(0);
                                    if($q !== null)
                                    {
                                        $q = $q->plaintext;
                                    }
                                }
                            }
                        }
                        $a = $element->find("div",0);
                        if($a !== null)
                        {
                            $a = $a->children(1);
                            if($a !== null)
                            {
                                $a = $a->children(0);
                                if($a !== null)
                                {
                                    $a = $a->children(0);
                                    if($a !== null)
                                    {
                                        $a = $a->children(0);
                                        if($a !== null)
                                        {
                                            $a = $a->plaintext;
                                        }
                                    }
                                }
                            }
                        }
                        $l = $element->find("div",0);
                        if($l !== null)
                        {
                            $l = $l->children(1);
                            if($l !== null)
                            {
                                $l = $l->children(0);
                                if($l !== null)
                                {
                                    $l = $l->children(0);
                                    if($l !== null)
                                    {
                                        $l = $l->children(1);
                                        if($l !== null)
                                        {
                                            $l = $l->children(0);
                                            if($l !== null)
                                            {
                                                $l = $l->children(0);
                                                if($l !== null)
                                                {
                                                    $l = $l->children(0);
                                                    if($l !== null)
                                                    {
                                                        $l = $l->children(0);
                                                        if($l !== null)
                                                        {
                                                            $l = $l->getAttribute('href');
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if($q !== null && $a !== null && $l !== null)
                        {
                            $rec = array("q" => $q, "a" => $a, "l" => $l);
                            if(!isset($results[$q]))
                            {
                                $results[$q] = $rec;
                            }
                            if(count($results) >= $headings)
                            {
                                break;
                            }
                        }
                        else
                        {
                            break;
                        }
                    }
                    $html_dom_original_html->clear();
                    unset($html_dom_original_html);
                }
            }
            if(count($results) > 0 && count($results) < $headings)
            {
                $ok = true;
                while($ok && count($results) < $headings)
                {
                    $last_elem = end($results);
                    sleep(1);
                    $url = "https://www.bing.com/search?q=" . urlencode($last_elem['q']);
                    $html_data = aiomatic_get_web_page($url);
                    if ($html_data !== FALSE) 
                    {
                        $html_dom_original_html = aiomatic_str_get_html($html_data);
                        if($html_dom_original_html !== false && method_exists($html_dom_original_html, 'find'))
                        {
                            $ret = $html_dom_original_html->find( trim($related_expre) );
                            if(!is_array($ret) || count($ret) == 0)
                            {
                                $html_dom_original_html->clear();
                                unset($html_dom_original_html);
                                break;
                            }
                            $count_before = count($results);
                            foreach ($ret as $element ) 
                            {
                                $q = $element->find("div",0);
                                if($q !== null)
                                {
                                    $q = $q->children(0);
                                    if($q !== null)
                                    {
                                        $q = $q->children(0);
                                        if($q !== null)
                                        {
                                            $q = $q->children(0);
                                            if($q !== null)
                                            {
                                                $q = $q->plaintext;
                                            }
                                        }
                                    }
                                }
                                $a = $element->find("div",0);
                                if($a !== null)
                                {
                                    $a = $a->children(1);
                                    if($a !== null)
                                    {
                                        $a = $a->children(0);
                                        if($a !== null)
                                        {
                                            $a = $a->children(0);
                                            if($a !== null)
                                            {
                                                $a = $a->children(0);
                                                if($a !== null)
                                                {
                                                    $a = $a->plaintext;
                                                }
                                            }
                                        }
                                    }
                                }
                                $l = $element->find("div",0);
                                if($l !== null)
                                {
                                    $l = $l->children(1);
                                    if($l !== null)
                                    {
                                        $l = $l->children(0);
                                        if($l !== null)
                                        {
                                            $l = $l->children(0);
                                            if($l !== null)
                                            {
                                                $l = $l->children(1);
                                                if($l !== null)
                                                {
                                                    $l = $l->children(0);
                                                    if($l !== null)
                                                    {
                                                        $l = $l->children(0);
                                                        if($l !== null)
                                                        {
                                                            $l = $l->children(0);
                                                            if($l !== null)
                                                            {
                                                                $l = $l->children(0);
                                                                if($l !== null)
                                                                {
                                                                    $l = $l->getAttribute('href');
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                if($q !== null && $a !== null && $l !== null)
                                {
                                    $rec = array("q" => $q, "a" => $a, "l" => $l);
                                    if(!isset($results[$q]))
                                    {
                                        $results[$q] = $rec;
                                    }
                                    if(count($results) >= $headings)
                                    {
                                        break;
                                    }
                                }
                                else
                                {
                                    break;
                                }
                            }
                            $count_after = count($results);
                            if($count_after == $count_before)
                            {
                                $ok = false;
                            }
                            $html_dom_original_html->clear();
                            unset($html_dom_original_html);
                        }
                        else
                        {
                            $ok = false;
                        }
                    }
                    else
                    {
                        $ok == false;
                    }
                }
            }
        }
    }
    if ((!isset($aiomatic_Main_Settings['ai_off']) || trim($aiomatic_Main_Settings['ai_off']) != 'on') && $model !== '' && $headings_ai_command !== '')
    {
        if(count($results) < $headings)
        {
            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
            if (!isset($aiomatic_Main_Settings['app_id']) || trim($aiomatic_Main_Settings['app_id']) == '') 
            {
                return $results;
            }
            $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
            $appids = array_filter($appids);
            $token = $appids[array_rand($appids)];
            $token = apply_filters('aiomatic_openai_api_key', $token);
            if(empty($headings_ai_command))
            {
                $headings_ai_command = 'Write ' . ($headings - count($results)) . ' PAA related questions, each on a new line, for the title: "' . $query . '"';
            }
            else
            {
                $headings_ai_command = str_replace('%%needed_heading_count%%', $headings - count($results), $headings_ai_command);
                $headings_ai_command = str_replace('%%post_title%%', $query, $headings_ai_command);
            }
            $query_token_count = count(aiomatic_encode($headings_ai_command));
            $available_tokens = aiomatic_compute_available_tokens($model, $max_tokens, $headings_ai_command, $query_token_count);
            if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
            {
                $string_len = strlen($headings_ai_command);
                $string_len = $string_len / 2;
                $string_len = intval(0 - $string_len);
                $headings_ai_command = aiomatic_substr($headings_ai_command, 0, $string_len);
                $headings_ai_command = trim($headings_ai_command);
                $query_token_count = count(aiomatic_encode($headings_ai_command));
                $available_tokens = $max_tokens - $query_token_count;
            }
            if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) 
            {
                $api_service = aiomatic_get_api_service($token, $model);
                aiomatic_log_to_file('Calling ' . $api_service . ' (' . $headings_assistant_id . '\\' . $model . ') for headings generator: ' . $headings_ai_command);
            }
            $thread_id = '';
            $aierror = '';
            $finish_reason = '';
            $generated_text = aiomatic_generate_text($token, $model, $headings_ai_command, $available_tokens, $temperature, $top_p, $presence_penalty, $frequency_penalty, false, 'shortcodeHeadingsArticle', 0, $finish_reason, $aierror, false, false, false, '', '', 'user', $headings_assistant_id, $thread_id, '', 'disabled', '', false, false);
            if($generated_text === false)
            {
                aiomatic_log_to_file('Title generator error: ' . $aierror);
                return $results;
            }
            else
            {
                $generated_text = ucfirst(trim(trim(trim(trim($generated_text), '.'), ' "\'')));
                $generated_text_arr = preg_split('/\r\n|\r|\n/', $generated_text);
                $generated_text_arr = array_filter($generated_text_arr);
                foreach($generated_text_arr as $gen_head)
                {
                    $rec = array("q" => $gen_head, "a" => '', "l" => '');
                    if(!isset($results[$gen_head]))
                    {
                        $results[$gen_head] = $rec;
                    }
                    if(count($results) >= $headings)
                    {
                        break;
                    }
                }
            }
        }
    }
    return $results;
}
function aiomatic_check_if_available_token_recalc_needed($model, $aiomatic_Main_Settings)
{
    if(aiomatic_check_if_azure($aiomatic_Main_Settings))
    {
        return true;
    }
    if(!aiomatic_is_o1_model($model) && !aiomatic_is_chatgpt_o_model($model) && !aiomatic_is_chatgpt_turbo_model($model) && !aiomatic_is_chatgpt35_16k_context_model($model) && !aiomatic_is_claude_model($model) && !aiomatic_is_openrouter_model($model) && !aiomatic_is_huggingface_model($model) && !aiomatic_is_ollama_model($model) && !aiomatic_is_perplexity_model($model) && !aiomatic_is_groq_model($model) && !aiomatic_is_nvidia_model($model) && !aiomatic_is_xai_model($model))
    {
        return true;
    }
    return false;
}
function aiomatic_is_aiomaticapi_key($token)
{
    if(empty($token))
    {
        return false;
    }
    $token_prepro = explode('_', $token);
    if(isset($token_prepro[1]) && strlen($token_prepro[1]) > 10 && is_numeric($token_prepro[0]))
    {
        return true;
    }
    return false;
}
function aiomatic_is_trained_model($model)
{
    if(stristr($model, ':ft-') !== false || aiomatic_starts_with($model, 'ft:') !== false)
    {
        return true;
    }
    return false;
}
function aiomatic_get_internet_embeddings_result($aiomatic_Main_Settings, $env, &$embeddings_enabled, &$internet_enabled, &$embeddings_namespace)
{
    if(stristr($env, 'singlePostWriter') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_single']) && $aiomatic_Main_Settings['embeddings_single'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_single_namespace']) && $aiomatic_Main_Settings['embeddings_single_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_single_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_single']) && $aiomatic_Main_Settings['internet_single'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'shortcodeHeadingsArticle') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_related']) && $aiomatic_Main_Settings['embeddings_related'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_related_namespace']) && $aiomatic_Main_Settings['embeddings_related_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_related_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_related']) && $aiomatic_Main_Settings['internet_related'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'formsText') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_forms']) && $aiomatic_Main_Settings['embeddings_forms'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_forms_namespace']) && $aiomatic_Main_Settings['embeddings_forms_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_forms_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_forms']) && $aiomatic_Main_Settings['internet_forms'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'omniBlocks') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_omni']) && $aiomatic_Main_Settings['embeddings_omni'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_omni_namespace']) && $aiomatic_Main_Settings['embeddings_omni_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_omni_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_omni']) && $aiomatic_Main_Settings['internet_omni'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'aiAssistantWriter') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_assistant']) && $aiomatic_Main_Settings['embeddings_assistant'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_assistant_namespace']) && $aiomatic_Main_Settings['embeddings_assistant_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_assistant_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_assistant']) && $aiomatic_Main_Settings['internet_assistant'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'shortcodeContentArticle') !== false || stristr($env, 'shortcodeHeadingArticle') !== false || stristr($env, 'shortcodeKeywordArticle') !== false || stristr($env, 'shortcodeCompletion') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_article_short']) && $aiomatic_Main_Settings['embeddings_article_short'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_article_short_namespace']) && $aiomatic_Main_Settings['embeddings_article_short_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_article_short_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_article_short']) && $aiomatic_Main_Settings['internet_article_short'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'shortcodeChat') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_chat_short']) && $aiomatic_Main_Settings['embeddings_chat_short'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_chat_short_namespace']) && $aiomatic_Main_Settings['embeddings_chat_short_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_chat_short_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_chat_short']) && $aiomatic_Main_Settings['internet_chat_short'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'shortcodeCEditor') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_edit_short']) && $aiomatic_Main_Settings['embeddings_edit_short'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_edit_short_namespace']) && $aiomatic_Main_Settings['embeddings_edit_short_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_edit_short_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_edit_short']) && $aiomatic_Main_Settings['internet_edit_short'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'keywordCompletion') !== false || stristr($env, 'titleCEditor') !== false || stristr($env, 'contentCEditor') !== false || stristr($env, 'contentCompletion') !== false || stristr($env, 'headingCompletion') !== false || stristr($env, 'excerptCEditor') !== false || stristr($env, 'slugCEditor') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_edit']) && $aiomatic_Main_Settings['embeddings_edit'] == 'on')
        {
            $embeddings_enabled = true;
            if(isset($aiomatic_Main_Settings['embeddings_edit_namespace']) && $aiomatic_Main_Settings['embeddings_edit_namespace'] != '')
            {
                $embeddings_namespace = $aiomatic_Main_Settings['embeddings_edit_namespace'];
            }
        }
        if(isset($aiomatic_Main_Settings['internet_edit']) && $aiomatic_Main_Settings['internet_edit'] == 'on')
        {
            $internet_enabled = true;
        }
    }
    elseif(stristr($env, 'tagID') !== false || stristr($env, 'categoryID') !== false || stristr($env, 'keywordID') !== false || stristr($env, 'titleID') !== false || stristr($env, 'contentID') !== false || stristr($env, 'headingID') !== false || stristr($env, 'topicContentWriter') !== false)
    {
        if(isset($aiomatic_Main_Settings['embeddings_bulk']) && $aiomatic_Main_Settings['embeddings_bulk'] == 'on')
        {
            if(stristr($env, 'titleID') !== false || stristr($env, 'topicContentWritertitle') !== false)
            {
                if(isset($aiomatic_Main_Settings['embeddings_bulk_title']) && $aiomatic_Main_Settings['embeddings_bulk_title'] == 'on')
                {
                    $embeddings_enabled = true;
                    if(isset($aiomatic_Main_Settings['embeddings_bulk_title_namespace']) && $aiomatic_Main_Settings['embeddings_bulk_title_namespace'] != '')
                    {
                        $embeddings_namespace = $aiomatic_Main_Settings['embeddings_bulk_title_namespace'];
                    }
                }
            }
            elseif(stristr($env, 'contentID') !== false || stristr($env, 'topicContentWritercontent') !== false)
            {
                if(isset($aiomatic_Main_Settings['embeddings_bulk_content']) && $aiomatic_Main_Settings['embeddings_bulk_content'] == 'on')
                {
                    $embeddings_enabled = true;
                    if(isset($aiomatic_Main_Settings['embeddings_bulk_content_namespace']) && $aiomatic_Main_Settings['embeddings_bulk_content_namespace'] != '')
                    {
                        $embeddings_namespace = $aiomatic_Main_Settings['embeddings_bulk_content_namespace'];
                    }
                }
            }
            elseif(stristr($env, 'headingID') !== false || stristr($env, 'topicContentWritersections') !== false)
            {
                if(isset($aiomatic_Main_Settings['embeddings_bulk_sections']) && $aiomatic_Main_Settings['embeddings_bulk_sections'] == 'on')
                {
                    $embeddings_enabled = true;
                    if(isset($aiomatic_Main_Settings['embeddings_bulk_sections_namespace']) && $aiomatic_Main_Settings['embeddings_bulk_sections_namespace'] != '')
                    {
                        $embeddings_namespace = $aiomatic_Main_Settings['embeddings_bulk_sections_namespace'];
                    }
                }
            }
            elseif(stristr($env, 'topicContentWriterintro') !== false)
            {
                if(isset($aiomatic_Main_Settings['embeddings_bulk_intro']) && $aiomatic_Main_Settings['embeddings_bulk_intro'] == 'on')
                {
                    $embeddings_enabled = true;
                    if(isset($aiomatic_Main_Settings['embeddings_bulk_intro_namespace']) && $aiomatic_Main_Settings['embeddings_bulk_intro_namespace'] != '')
                    {
                        $embeddings_namespace = $aiomatic_Main_Settings['embeddings_bulk_intro_namespace'];
                    }
                }
            }
            elseif(stristr($env, 'topicContentWriterqa') !== false)
            {
                if(isset($aiomatic_Main_Settings['embeddings_bulk_qa']) && $aiomatic_Main_Settings['embeddings_bulk_qa'] == 'on')
                {
                    $embeddings_enabled = true;
                    if(isset($aiomatic_Main_Settings['embeddings_bulk_qa_namespace']) && $aiomatic_Main_Settings['embeddings_bulk_qa_namespace'] != '')
                    {
                        $embeddings_namespace = $aiomatic_Main_Settings['embeddings_bulk_qa_namespace'];
                    }
                }
            }
            elseif(stristr($env, 'topicContentWriteroutro') !== false)
            {
                if(isset($aiomatic_Main_Settings['embeddings_bulk_outro']) && $aiomatic_Main_Settings['embeddings_bulk_outro'] == 'on')
                {
                    $embeddings_enabled = true;
                    if(isset($aiomatic_Main_Settings['embeddings_bulk_outro_namespace']) && $aiomatic_Main_Settings['embeddings_bulk_outro_namespace'] != '')
                    {
                        $embeddings_namespace = $aiomatic_Main_Settings['embeddings_bulk_outro_namespace'];
                    }
                }
            }
            elseif(stristr($env, 'topicContentWriterexcerpt') !== false)
            {
                if(isset($aiomatic_Main_Settings['embeddings_bulk_excerpt']) && $aiomatic_Main_Settings['embeddings_bulk_excerpt'] == 'on')
                {
                    $embeddings_enabled = true;
                    if(isset($aiomatic_Main_Settings['embeddings_bulk_excerpt_namespace']) && $aiomatic_Main_Settings['embeddings_bulk_excerpt_namespace'] != '')
                    {
                        $embeddings_namespace = $aiomatic_Main_Settings['embeddings_bulk_excerpt_namespace'];
                    }
                }
            }
            else 
            {
                $embeddings_enabled = true;
                if(isset($aiomatic_Main_Settings['embeddings_bulk_content_namespace']) && $aiomatic_Main_Settings['embeddings_bulk_content_namespace'] != '')
                {
                    $embeddings_namespace = $aiomatic_Main_Settings['embeddings_bulk_content_namespace'];
                }
            }
        }
        if(isset($aiomatic_Main_Settings['internet_bulk']) && $aiomatic_Main_Settings['internet_bulk'] == 'on')
        {
            if(stristr($env, 'titleID') !== false || stristr($env, 'topicContentWritertitle') !== false)
            {
                if(isset($aiomatic_Main_Settings['internet_bulk_title']) && $aiomatic_Main_Settings['internet_bulk_title'] == 'on')
                {
                    $internet_enabled = true;
                }
            }
            elseif(stristr($env, 'contentID') !== false || stristr($env, 'topicContentWritercontent') !== false)
            {
                if(isset($aiomatic_Main_Settings['internet_bulk_content']) && $aiomatic_Main_Settings['internet_bulk_content'] == 'on')
                {
                    $internet_enabled = true;
                }
            }
            elseif(stristr($env, 'headingID') !== false || stristr($env, 'topicContentWritersections') !== false)
            {
                if(isset($aiomatic_Main_Settings['internet_bulk_sections']) && $aiomatic_Main_Settings['internet_bulk_sections'] == 'on')
                {
                    $internet_enabled = true;
                }
            }
            elseif(stristr($env, 'topicContentWriterintro') !== false)
            {
                if(isset($aiomatic_Main_Settings['internet_bulk_intro']) && $aiomatic_Main_Settings['internet_bulk_intro'] == 'on')
                {
                    $internet_enabled = true;
                }
            }
            elseif(stristr($env, 'topicContentWriterqa') !== false)
            {
                if(isset($aiomatic_Main_Settings['internet_bulk_qa']) && $aiomatic_Main_Settings['internet_bulk_qa'] == 'on')
                {
                    $internet_enabled = true;
                }
            }
            elseif(stristr($env, 'topicContentWriteroutro') !== false)
            {
                if(isset($aiomatic_Main_Settings['internet_bulk_outro']) && $aiomatic_Main_Settings['internet_bulk_outro'] == 'on')
                {
                    $internet_enabled = true;
                }
            }
            elseif(stristr($env, 'topicContentWriterexcerpt') !== false)
            {
                if(isset($aiomatic_Main_Settings['internet_bulk_excerpt']) && $aiomatic_Main_Settings['internet_bulk_excerpt'] == 'on')
                {
                    $internet_enabled = true;
                }
            }
            else 
            {
                $internet_enabled = true;
            }
        }
    }
}
function aiomatic_add_proxy($curl)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['proxy_ai']) && $aiomatic_Main_Settings['proxy_ai'] == 'on' && isset($aiomatic_Main_Settings['proxy_url']) && $aiomatic_Main_Settings['proxy_url'] != '' && $aiomatic_Main_Settings['proxy_url'] != 'disable' && $aiomatic_Main_Settings['proxy_url'] != 'disabled')
    {
        $prx = explode(',', $aiomatic_Main_Settings['proxy_url']);
        $randomness = array_rand($prx);
        curl_setopt($curl, CURLOPT_PROXY , trim($prx[$randomness]));
        if (isset($aiomatic_Main_Settings['proxy_auth']) && $aiomatic_Main_Settings['proxy_auth'] != '') 
        {
            $prx_auth = explode(',', $aiomatic_Main_Settings['proxy_auth']);
            if(isset($prx_auth[$randomness]) && trim($prx_auth[$randomness]) != '')
            {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD , trim($prx_auth[$randomness]));
            }
        }
    }
}

function aiomatic_check_if_azure($aiomatic_Main_Settings)
{
    if(isset($aiomatic_Main_Settings['api_selector']) && trim($aiomatic_Main_Settings['api_selector']) == 'azure')
    {
        return true;
    }
    return false;
}
function aiomatic_check_if_stable($model)
{
    if(in_array($model, AIOMATIC_STABLE_IMAGE_MODELS))
    {
        return true;
    }
    return false;
}
function aiomatic_check_if_midjourney($model)
{
    if($model == 'fast' || $model == 'mixed' || $model == 'turbo')
    {
        return true;
    }
    return false;
}
function aiomatic_check_if_replicate($model)
{
    if(strlen($model) == 64)
    {
        return true;
    }
    return false;
}
function aiomatic_check_if_azure_or_others($aiomatic_Main_Settings, $model = '')
{
    if(isset($aiomatic_Main_Settings['api_selector']) && (trim($aiomatic_Main_Settings['api_selector']) == 'azure'))
    {
        return true;
    }
    if (!empty($model)) 
    {
        if(aiomatic_is_claude_model($model) || aiomatic_is_google_model($model) || aiomatic_is_perplexity_model($model) || aiomatic_is_groq_model($model) || aiomatic_is_nvidia_model($model) || aiomatic_is_xai_model($model))
        {
            return true;
        }
    }
    return false;
}
function aiomatic_is_request_allowed() 
{
    $aiomatic_Limit_Settings = get_option('aiomatic_Limit_Settings', false);
    if (isset($aiomatic_Limit_Settings['block_userids']) && $aiomatic_Limit_Settings['block_userids'] != '')
    {
        $curid = get_current_user_id();
        if($curid != 0)
        {
            $blist = explode(',', $aiomatic_Limit_Settings['block_userids']);
            $blist = array_map('trim', $blist);
            foreach($blist as $belem)
            {
                if(intval($belem) === $curid)
                {
                    return false;
                }
            }
        }
    }
    return true;
}
add_filter( 'aiomatic_is_ai_query_allowed', 'aiomatic_is_request_allowed' );
add_filter( 'aiomatic_is_ai_edit_allowed', 'aiomatic_is_request_allowed' );
add_filter( 'aiomatic_is_ai_image_allowed', 'aiomatic_is_request_allowed' );
add_filter( 'aiomatic_is_ai_video_allowed', 'aiomatic_is_request_allowed' );
function aiomatic_parse_markdown($text) 
{
    // Headers
    $text = preg_replace('/^###### (.+?)$/m', '<h6>$1</h6>', $text);
    $text = preg_replace('/^##### (.+?)$/m', '<h5>$1</h5>', $text);
    $text = preg_replace('/^#### (.+?)$/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^### (.+?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.+?)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.+?)$/m', '<h1>$1</h1>', $text);

    // Bold
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);

    // Italic
    $text = preg_replace('/\*([^\*]+?)\*/s', '<em>$1</em>', $text);
    $text = preg_replace('/_([^_]+?)_/s', '<em>$1</em>', $text);

    // Strikethrough
    $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);

    // Code Block
    $text = preg_replace('/```(.+?)```/s', '<pre><code>$1</code></pre>', $text);

    // Inline Code
    $text = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $text);

    // Link
    $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);

    // Images
    $text = preg_replace('/!\[(.+?)\]\((.+?)\)/', '<img src="$2" alt="$1">', $text);

    // Horizontal Rule
    $text = preg_replace('/^-{3,}$/m', '<hr>', $text);

    // Blockquote
    $text = preg_replace('/^> (.+?)$/m', '<blockquote>$1</blockquote>', $text);

    // Lists
    $lines = explode("\n", $text);
    $inList = false;
    $inOrderedList = false;
    $text = '';

    foreach ($lines as $line) {
        if (preg_match('/^- (.+?)$/', $line)) { 
            if (!$inList) { 
                $inList = true;
                $text .= "<ul>\n";
            }
            $text .= '<li>' . preg_replace('/^- (.+?)$/', '$1', $line) . "</li>\n"; 
        } elseif (preg_match('/^[0-9]+\. (.+?)$/', $line)) {
            if (!$inOrderedList) { 
                $inOrderedList = true;
                $text .= "<ol>\n";
            }
            $text .= '<li>' . preg_replace('/^[0-9]+\. (.+?)$/', '$1', $line) . "</li>\n"; 
        } else {
            if ($inList) { 
                $inList = false;
                $text .= "</ul>\n";
            }
            if ($inOrderedList) { 
                $inOrderedList = false;
                $text .= "</ol>\n";
            }
            $text .= $line . "\n"; 
        }
    }

    if ($inList) { 
        $text .= "</ul>\n";
    }
    if ($inOrderedList) { 
        $text .= "</ol>\n";
    }

    return $text;
}
function aiomatic_find_local_assistant_id($assistant_id)
{
    $return_id = false;
    $args = array(
        'post_type'      => 'aiomatic_assistants',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => '_assistant_id', 
                'value'   => $assistant_id,
                'compare' => '=', 
            )
        )
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) 
    {
        while ($query->have_posts()) 
        {
            $query->the_post();
            $return_id = get_the_ID();
        }
    }
    wp_reset_postdata();
    return $return_id;
}

function aiomatic_get_models_xai()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $appids_xai = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id_xai']));
    $appids_xai = array_filter($appids_xai);
    $token = $appids_xai[array_rand($appids_xai)];
    add_action('http_api_curl', 'aiomatic_add_proxy');
    $api_call = wp_remote_post(
        'https://api.x.ai/v1/models',
        array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'method'      => 'GET',
            'data_format' => 'body',
            'timeout'     => AIOMATIC_DEFAULT_BIG_TIMEOUT,
        )
    );
    remove_action('http_api_curl', 'aiomatic_add_proxy');
    $result = json_decode( $api_call['body'] );
    return $result;
}
function aiomatic_get_embedding_models_xai()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $appids_xai = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id_xai']));
    $appids_xai = array_filter($appids_xai);
    $token = $appids_xai[array_rand($appids_xai)];
    add_action('http_api_curl', 'aiomatic_add_proxy');
    $api_call = wp_remote_post(
        'https://api.x.ai/v1/embedding-models',
        array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'method'      => 'GET',
            'data_format' => 'body',
            'timeout'     => AIOMATIC_DEFAULT_BIG_TIMEOUT,
        )
    );
    remove_action('http_api_curl', 'aiomatic_add_proxy');
    $result = json_decode( $api_call['body'] );
    return $result;
}
function aiomatic_run_functions(&$token, $model, $aicontent, $available_tokens, $temperature, $top_p, $presence_penalty, $frequency_penalty, $is_chat, $env, $retry_count, &$finish_reason, &$error, $no_internet = false, $no_embeddings = false, $stream = false, $vision_file = '', $user_question = '', $role = 'user', $assistant_id = '', &$thread_id = '', $embedding_namespace = '', $function_result = '', $file_data = '', $store_data = false)
{
    if(empty($model))
    {
        $model = AIOMATIC_DEFAULT_MODEL;
    }
    $is_allowed = apply_filters('aiomatic_is_ai_query_allowed', true, $aicontent);
    if ( $is_allowed !== true ) {
        $error = is_string( $is_allowed ) ? $is_allowed : esc_html__('You are not allowed to do this query', 'aiomatic-automatic-ai-content-writer');
        $error = apply_filters('aiomatic_modify_ai_error', $error);
        return false;
    }
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $aicontent = apply_filters('aiomatic_modify_ai_query', $aicontent);
    if(empty($function_result))
    {
        if($is_chat)
        {
            $model_parts = explode(':', $model);
            $checkmodel = $model_parts[0];
            if(in_array($model, AIOMATIC_FUNCTION_CALLING_MODELS) || in_array($model, AIOMATIC_GROQ_FUNCTION_CALLING_MODELS) || in_array($model, AIOMATIC_XAI_FUNCTION_CALLING_MODELS) || in_array($checkmodel, AIOMATIC_OLLAMA_FUNCTION_CALLING_MODELS))
            {
                require_once(dirname(__FILE__) . "/aiomatic-god-mode.php");
                require_once(dirname(__FILE__) . "/aiomatic-god-mode-parser.php");
            }
        }
    }
    else
    {
        if($function_result != 'disabled')
        {
            if($is_chat)
            {
                remove_filter('aiomatic_ai_functions', 'aiomatic_add_god_mode', 999);
            }
        }
    }
    $functions = apply_filters('aiomatic_ai_functions', false);
    if(!empty($functions) && is_array($functions))
    {
        if(isset($functions['functions']) && is_array($functions['functions']))
        {
            if(aiomatic_check_if_available_token_recalc_needed($model, $aiomatic_Main_Settings))
            {
                $total_used_tokens = 0;
                foreach($functions['functions'] as $func_count)
                {
                    if(isset($func_count['function']->name))
                    {
                        $total_used_tokens += count(aiomatic_encode($func_count['function']->name));
                    }
                    if(isset($func_count['function']->description))
                    {
                        $total_used_tokens += count(aiomatic_encode($func_count['function']->description));
                    }
                    if(isset($func_count['function']->parameters) && is_array($func_count['function']->parameters))
                    {
                        foreach($func_count['function']->parameters as $fpar)
                        {
                            if(isset($fpar->name))
                            {
                                $total_used_tokens += count(aiomatic_encode($fpar->name));
                            }
                            if(isset($fpar->description))
                            {
                                $total_used_tokens += count(aiomatic_encode($fpar->description));
                            }
                        }
                    }
                }
                $available_tokens = $available_tokens - $total_used_tokens;
                if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_CHAT)
                {
                    if(is_string($aicontent))
                    {
                        $string_len = strlen($aicontent);
                        $string_len = $string_len / 2;
                        $string_len = intval(0 - $string_len);
                        $aicontent = aiomatic_substr($aicontent, 0 - $string_len);
                        $aicontent = trim($aicontent);
                        if(empty($aicontent))
                        {
                            $error = 'Incorrect chat prompt provided(2): ' . $aicontent;
                            $error = apply_filters('aiomatic_modify_ai_error', $error);
                            return false;
                        }
                        $query_token_count = count(aiomatic_encode($aicontent));
                        $max_tokens = aiomatic_get_max_tokens($model);
                        $available_tokens = $max_tokens - $query_token_count;
                    }
                    else
                    {
                        $aitext = '';
                        foreach($aicontent as $aimess)
                        {
                            if(isset($aimess['content']))
                            {
                                if(!is_array($aimess['content']))
                                {
                                    $aitext .= $aimess['content'] . '\n';
                                }
                                else
                                {
                                    foreach($aimess['content'] as $internalmess)
                                    {
                                        if($internalmess['type'] == 'text')
                                        {
                                            $aitext .= $internalmess['text'] . '\n';
                                        }
                                    }
                                }
                            }
                        }
                        $max_tokens = aiomatic_get_max_tokens($model);
                        $query_token_count = count(aiomatic_encode($aitext));
                        $available_tokens = aiomatic_compute_available_tokens($model, $max_tokens, $aitext, $query_token_count);
                        if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
                        {
                            $startIndex = intdiv(count($aicontent), 2);
                            $aicontent = array_slice($aicontent, $startIndex);
                            $lastindex = end(array_keys($aicontent));
                            $string_len = strlen($aicontent[$lastindex]['content']);
                            $string_len = $string_len / 2;
                            $string_len = intval(0 - $string_len);
                            $aicontent[$lastindex]['content'] = aiomatic_substr($aicontent[$lastindex]['content'], 0, $string_len);
                            $aicontent[$lastindex]['content'] = trim($aicontent[$lastindex]['content']);
                            $aitext = '';
                            foreach($aicontent as $aimess)
                            {
                                if(isset($aimess['content']))
                                {
                                    if(!is_array($aimess['content']))
                                    {
                                        $aitext .= $aimess['content'] . '\n';
                                    }
                                    else
                                    {
                                        foreach($aimess['content'] as $internalmess)
                                        {
                                            if($internalmess['type'] == 'text')
                                            {
                                                $aitext .= $internalmess['text'] . '\n';
                                            }
                                        }
                                    }
                                }
                            }
                            $query_token_count = count(aiomatic_encode($aitext));
                            $available_tokens = $max_tokens - $query_token_count;
                        }
                    }
                }
            }
        }
    }
    if ( empty($functions) ) 
    {
        $error = esc_html__('Empty functions list provided!', 'aiomatic-automatic-ai-content-writer');
        $error = apply_filters('aiomatic_modify_ai_error', $error);
        return false;
    }
    if(isset($aiomatic_Main_Settings['multiple_key']) && $aiomatic_Main_Settings['multiple_key'] == 'on')
    {
        $token = aiomatic_try_new_key(trim($aiomatic_Main_Settings['app_id']), $token);
    }
    if ( empty($token) ) 
    {
        $error = esc_html__('Empty API key provided!', 'aiomatic-automatic-ai-content-writer');
        $error = apply_filters('aiomatic_modify_ai_error', $error);
        return false;
    }
    if(empty($user_question))
    {
        if(is_array($aicontent))
        {
            $lastindex = end(array_keys($aicontent));
            if(isset($aicontent[$lastindex]['content']))
            {
                $user_question = $aicontent[$lastindex]['content'];
            }
        }
        else
        {
            $user_question = $aicontent;
        }
    }
    if(empty($assistant_id))
    {
        $assistant_id = '';
    }
    if(!empty(trim($assistant_id)) && !aiomatic_is_aiomaticapi_key($token) && !(aiomatic_check_if_azure_or_others($aiomatic_Main_Settings, $model)))
    {
        if(!aiomatic_is_vision_model('', $assistant_id) && $vision_file != '')
        {
            $vision_file = '';
        }
        try
        {
            $local_assistant_id = '';
            if(is_numeric($assistant_id))
            {
                $assistant_id_temp = get_post_meta($assistant_id, '_assistant_id', true);
                if(!empty($assistant_id_temp))
                {
                    $local_assistant_id = $assistant_id;
                    $assistant_id = $assistant_id_temp;
                }
            }
            $response_ai = aiomatic_generate_text_assistant($token, $assistant_id, $local_assistant_id, $role, $user_question, $thread_id, $no_internet, $no_embeddings, $env, 0, $embedding_namespace, $stream, $function_result, $vision_file, $file_data);
            if(isset($response_ai['content'][0]['text']['value']))
            {
                $response_text = $response_ai['content'][0]['text']['value'];
            }
            else
            {
                throw new Exception('Cannot parse AI response: ' . print_r($response_ai, true));
            }
        }
        catch(Exception $e)
        {
            $error = 'Error in AI (' . $assistant_id . '): ' . $e->getMessage();
            apply_filters('aiomatic_modify_ai_error', $error);
            return false;
        }
        if($response_text === false || empty($response_text))
        {
            $error = apply_filters('aiomatic_modify_ai_error', $error);
            return false;
        }
        if($is_chat == false)
        {
            $response_text = aiomatic_clean_language_model_texts($response_text);
            $response_text = trim($response_text);
        }
    }
    else
    {
        if(!aiomatic_is_vision_model($model, '') && $vision_file != '')
        {
            $vision_file = '';
        }
        if(aiomatic_is_chatgpt_model($model) || aiomatic_is_chatgpt_turbo_model($model) || aiomatic_is_perplexity_model($model) || aiomatic_is_groq_model($model) || aiomatic_is_nvidia_model($model) || aiomatic_is_xai_model($model) || aiomatic_is_chatgpt_o_model($model) || aiomatic_is_o1_model($model))
        {
            if(is_array($aicontent))
            {
                $chatgpt_obj = $aicontent;
            }
            else
            {
                $role = 'user';
                $chatgpt_obj = array();
                $chatgpt_obj[] = array("role" => $role, "content" => $aicontent);
                $additional_tokens = count(aiomatic_encode($role . ': '));
                if(aiomatic_check_if_available_token_recalc_needed($model, $aiomatic_Main_Settings))
                {
                    if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_CHAT)
                    {
                        $string_len = strlen($aicontent);
                        $string_len = $string_len / 2;
                        $string_len = intval(0 - $string_len);
                        $aicontent = aiomatic_substr($aicontent, 0 - $string_len);
                        $aicontent = trim($aicontent);
                        if(empty($aicontent))
                        {
                            $error = 'Incorrect chat prompt provided: ' . $aicontent;
                            $error = apply_filters('aiomatic_modify_ai_error', $error);
                            return false;
                        }
                        $query_token_count = count(aiomatic_encode($aicontent));
                        $max_tokens = aiomatic_get_max_tokens($model);
                        $available_tokens = $max_tokens - $query_token_count;
                        $chatgpt_obj = array();
                        $chatgpt_obj[] = array("role" => $role, "content" => $aicontent);
                    }
                }
                if($available_tokens - $additional_tokens <= 0)
                {
                    $error = 'Not enough tokens for the call: ' . $aicontent;
                    $error = apply_filters('aiomatic_modify_ai_error', $error);
                    return false;
                }
                else
                {
                    if(aiomatic_check_if_available_token_recalc_needed($model, $aiomatic_Main_Settings))
                    {
                        $available_tokens = $available_tokens - $additional_tokens;
                    }
                }
            }
            $response_text = aiomatic_generate_text_chat($token, $model, $chatgpt_obj, $available_tokens, $temperature, $top_p, $presence_penalty, $frequency_penalty, $is_chat, $env, $retry_count, $finish_reason, $error, $no_internet, $no_embeddings, $functions, $stream, $vision_file, false, $user_question, $embedding_namespace, $function_result, true);
            if($response_text === false || empty($response_text))
            {
                $error = apply_filters('aiomatic_modify_ai_error', $error);
                return false;
            }
        }
        else
        {
            $error = 'The submitted model is not supported for function calls: ' . $model;
            $error = apply_filters('aiomatic_modify_ai_error', $error);
            return false;
        }
    }
    return $response_text;
}
function aiomatic_convertIntToStrings($array)
{
    if(!is_array($array))
    {
        return $array;
    }
    foreach ($array as &$value) 
    {
        if (is_int($value)) 
        {
            $value = (string) $value;
        } 
        elseif (is_array($value)) 
        {
            $value = aiomatic_convertIntToStrings($value);
        }
    }
    unset($value);
    return $array;
}
function aiomatic_get_default_model_name($aiomatic_Main_Settings)
{
    $model = 'gpt-4o-mini';
    if (isset($aiomatic_Main_Settings['default_ai_model']) && $aiomatic_Main_Settings['default_ai_model'] != '') 
    {
        $model = $aiomatic_Main_Settings['default_ai_model'];
    }
    return $model;
}
function aiomatic_filterCurlForStream($handle)
{
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($handle, CURLOPT_WRITEFUNCTION, function ($curl_info, $data) 
    {
        $my_copy_data = trim($data);
        $prefix = 'data: ';
        if (substr($my_copy_data, 0, strlen($prefix)) == $prefix) {
            $my_copy_data = substr($my_copy_data, strlen($prefix));
        }
        $suffix = 'data: [DONE]';
        $needle_length = strlen($suffix);
        if (substr($my_copy_data, -$needle_length) === $suffix) {
            $my_copy_data = substr($my_copy_data, 0, -$needle_length);
        }
        $my_copy_data = trim($my_copy_data);
        $response = json_decode($my_copy_data, true);
        if (isset($response['error']) && !empty($response['error'])) 
        {
            $message = isset($response['error']['message']) && !empty($response['error']['message']) ? $response['error']['message'] : '';
            if (empty($message) && isset($response['error']['code']) && $response['error']['code'] == 'invalid_api_key') {
                $message = "Incorrect API key provided. You can find your API key at https://platform.openai.com/account/api-keys.";
            }
            echo "event: message\n";
            echo 'data: {"error":[{"message":"' . $message . '"}]}';
            echo "\n\n";
            $l1 = ob_get_length();
            if($l1 === false)
            {
                $l1 = 0;
            }
            if (ob_get_length())
            {
                ob_end_flush();
            }
            flush();
            echo 'data: {"choices":[{"finish_reason":"stop"}]}';
            echo "\n\n";
            $l2 = ob_get_length();
            if($l2 === false)
            {
                $l2 = 0;
            }
            if (ob_get_length())
            {
                ob_end_flush();
            }
            flush();
            return $l1 + $l2;
        } 
        else 
        {
            echo $data;
            if (ob_get_length())
            {
                ob_flush();
            }
            flush();
            return strlen($data);
        }
    });
}
function aiomatic_get_models($token, $retry_count, &$error)
{
    $delay = '';
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (aiomatic_check_if_azure_or_others($aiomatic_Main_Settings)) 
    {
        $error = 'Only OpenAI/AiomaticAPI APIs are currently supported for model listing.';
        return false;
    }
    if (isset($aiomatic_Main_Settings['request_delay']) && $aiomatic_Main_Settings['request_delay'] != '') 
    {
        if(stristr($aiomatic_Main_Settings['request_delay'], ',') !== false)
        {
            $tempo = explode(',', $aiomatic_Main_Settings['request_delay']);
            if(isset($tempo[1]) && is_numeric(trim($tempo[1])) && is_numeric(trim($tempo[0])))
            {
                $delay = rand(trim($tempo[0]), trim($tempo[1]));
            }
        }
        else
        {
            if(is_numeric(trim($aiomatic_Main_Settings['request_delay'])))
            {
                $delay = intval(trim($aiomatic_Main_Settings['request_delay']));
            }
        }
    }
    if($delay != '' && is_numeric($delay))
    {
        usleep($delay);
    }
    if(aiomatic_is_aiomaticapi_key($token))
    {
        $pargs = array();
        $api_url = 'https://aiomaticapi.com/apis/ai/v1/models/';
        $pargs['apikey'] = trim($token);
        $ai_response = aiomatic_get_web_page_api($api_url, $pargs);
        if($ai_response === false)
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') AiomaticAPI model API call after initial failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_get_models($token, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to get AiomaticAPI response!';
                return false;
            }
        }
        $ai_json = json_decode($ai_response);
        if($ai_json === null)
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') AiomaticAPI model API call after decode failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_get_models($token, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to decode AiomaticAPI response: ' . $ai_response;
                return false;
            }
        }
        if(isset($ai_json->error))
        {
            if (stristr($ai_json->error, 'Your subscription expired, please renew it.') === false && stristr($ai_json->error, '[RATE LIMITED]') === false && isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') AiomaticAPI model API call after error failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_get_models($token, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error while processing AI response: ' . $ai_json->error;
                return false;
            }
        }
        if(!isset($ai_json->result))
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') AiomaticAPI model API call after result failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_get_models($token, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to parse AiomaticAPI response: ' . $ai_response;
                return false;
            }
        }
        if(isset($ai_json->remainingtokens))
        {
            set_transient('aiomaticapi_tokens', $ai_json->remainingtokens, 86400);
        }
        return $ai_json->result;
    }
    else
    {
        remove_action('http_api_curl', 'aiomatic_add_proxy');
        $xh = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        );
        if (isset($aiomatic_Main_Settings['openai_organization']) && $aiomatic_Main_Settings['openai_organization'] != '')
        {
            $xh['OpenAI-Organization'] = $aiomatic_Main_Settings['openai_organization'];
        }
        $api_call = wp_remote_get(
            'https://api.openai.com/v1/models',
            array(
                'headers' => $xh,
                'data_format' => 'body',
                'timeout'     => AIOMATIC_DEFAULT_BIG_TIMEOUT,
            )
        );
        remove_action('http_api_curl', 'aiomatic_add_proxy');
        if(is_wp_error( $api_call ))
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') model API call after initial failure: ' . print_r($api_call, true));
                sleep(pow(2, $retry_count));
                return aiomatic_get_models($token, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to get initial API response: ' . print_r($api_call, true);
                return false;
            }
        }
        else
        {
            $result = json_decode( $api_call['body'] );
            if($result === null)
            {
                if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                {
                    aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') model API call after decode failure: ' . print_r($api_call['body'], true));
                    sleep(pow(2, $retry_count));
                    return aiomatic_get_models($token, intval($retry_count) + 1, $error);
                }
                else
                {
                    $error = 'Error: Failed to decode initial API response: ' . print_r($api_call, true);
                    return false;
                }
            }
            if(isset($result->error))
            {
                $result = $result->error;
            }
            if(isset($result->type))
            {
                if($result->type == 'insufficient_quota')
                {
                    $error = 'Error: You exceeded your OpenAI general quota limit. To fix this, if you are using a free OpenAI account, you need to add a VISA card to your account, as OpenAI heavily limits free accounts. Please check details here: https://platform.openai.com/docs/guides/rate-limits';
                    return false;
                }
                elseif($result->type == 'invalid_request_error')
                {
                    $error = 'Error: Invalid request submitted to the models API! Result: ' . print_r($result, true);
                    return false;
                }
                else
                {
                    if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                    {
                        aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') model API call after type failure: ' . print_r($api_call['body'], true));
                        sleep(pow(2, $retry_count));
                        return aiomatic_get_models($token, intval($retry_count) + 1, $error);
                    }
                    else
                    {
                        $error = 'Error: An error occurred when initially calling OpenAI models API: ' . print_r($result, true);
                        return false;
                    }
                }
            }
            if(!isset($result->data[0]->id))
            {
                if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                {
                    aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') model API call after model listing failure: ' . print_r($api_call['body'], true));
                    sleep(pow(2, $retry_count));
                    return aiomatic_get_models($token, intval($retry_count) + 1, $error);
                }
                else
                {
                    if(isset($result->code) && $result->code == 'content_filter')
                    {
                        $error = 'Error: The response was filtered by our content management policy.';
                        return false;
                    }
                    else
                    {
                        $error = 'Error: Choices not found in initial API result: ' . print_r($result, true);
                        return false;
                    }
                }
            }
            else
            {
                return $result->data;
            }
        }
    }
    $error = 'Failed to finish API call correctly.';
    return false;
}

function aiomatic_edit_text($token, $model, $instruction, $aicontent, $temperature, $top_p, $env, $retry_count, &$error)
{
    $is_allowed = apply_filters('aiomatic_is_ai_edit_allowed', true, $instruction, $aicontent);
    if ( $is_allowed !== true ) {
        $error = is_string( $is_allowed ) ? $is_allowed : esc_html__('You are not allowed to do this query', 'aiomatic-automatic-ai-content-writer');
        $error = apply_filters('aiomatic_modify_ai_error', $error);
        return false;
    }
    $aicontent = apply_filters('aiomatic_modify_ai_edit_content', $instruction, $aicontent);
    $instruction = apply_filters('aiomatic_modify_ai_edit_instruction', $instruction, $aicontent);
    $aiomatic_Limit_Settings = get_option('aiomatic_Limit_Settings', false);
    $stop = null;
    $session = aiomatic_get_session_id();
    $mode = 'edit';
    $maxResults = 1;
    $available_tokens = 1000;
    $query = new Aiomatic_Query($aicontent, $available_tokens, $model, $temperature, $stop, $env, $mode, $token, $session, $maxResults, '', '');
    $ok = apply_filters( 'aiomatic_ai_allowed', true, $aiomatic_Limit_Settings );
    if ( $ok !== true ) {
        $error = $ok;
        $error = apply_filters('aiomatic_modify_ai_error', $error);
        return false;
    }
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (aiomatic_check_if_azure_or_others($aiomatic_Main_Settings, $model)) 
    {
        $error = 'Azure and Claude APIs are not currently supported for edit endpoints.';
        $error = apply_filters('aiomatic_modify_ai_error', $error);
        return false;
    }
    if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on' && AIOMATIC_IS_DEBUG === true)
    {
        aiomatic_log_to_file('Generating AI editing using model: ' . $model . ' using instruction: "' . $instruction . '" and text: "' . $aicontent . '"');
    }
    $delay = '';
    if (isset($aiomatic_Main_Settings['request_delay']) && $aiomatic_Main_Settings['request_delay'] != '') 
    {
        if(stristr($aiomatic_Main_Settings['request_delay'], ',') !== false)
        {
            $tempo = explode(',', $aiomatic_Main_Settings['request_delay']);
            if(isset($tempo[1]) && is_numeric(trim($tempo[1])) && is_numeric(trim($tempo[0])))
            {
                $delay = rand(trim($tempo[0]), trim($tempo[1]));
            }
        }
        else
        {
            if(is_numeric(trim($aiomatic_Main_Settings['request_delay'])))
            {
                $delay = intval(trim($aiomatic_Main_Settings['request_delay']));
            }
        }
    }
    if($delay != '' && is_numeric($delay))
    {
        usleep($delay);
    }
    if(aiomatic_is_aiomaticapi_key($token))
    {
        $pargs = array();
        $api_url = 'https://aiomaticapi.com/apis/ai/v1/edit/';
        $pargs['apikey'] = trim($token);
        $pargs['temperature'] = $temperature;
        $pargs['top_p'] = $top_p;
        $pargs['instruction'] = trim($instruction);
        $pargs['input'] = trim($aicontent);
        $pargs['model'] = trim($model);
        $ai_response = aiomatic_get_web_page_api($api_url, $pargs);
        if($ai_response === false)
        {
            $error = 'Error: Failed to get AiomaticAPI response!';
            $error = apply_filters('aiomatic_modify_ai_error', $error);
            return false;
        }
        $ai_json = json_decode($ai_response);
        if($ai_json === null)
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after decode edit failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_edit_text($token, $model, $instruction, $aicontent, $temperature, $top_p, $env, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to decode AiomaticAPI response: ' . $ai_response;
                $error = apply_filters('aiomatic_modify_ai_error', $error);
                return false;
            }
        }
        if(isset($ai_json->error))
        {
            if (stristr($ai_json->error, 'Your subscription expired, please renew it.') === false && stristr($ai_json->error, '[RATE LIMITED]') === false && isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after error edit failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_edit_text($token, $model, $instruction, $aicontent, $temperature, $top_p, $env, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error while processing AI response: ' . $ai_json->error;
                $error = apply_filters('aiomatic_modify_ai_error', $error);
                return false;
            }
        }
        if(!isset($ai_json->result))
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after result edit failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_edit_text($token, $model, $instruction, $aicontent, $temperature, $top_p, $env, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to parse AiomaticAPI response: ' . $ai_response;
                $error = apply_filters('aiomatic_modify_ai_error', $error);
                return false;
            }
        }
        if(isset($ai_json->remainingtokens))
        {
            set_transient('aiomaticapi_tokens', $ai_json->remainingtokens, 86400);
        }
        $ai_json = apply_filters( 'aiomatic_edit_reply_raw', $ai_json, $instruction, $aicontent );
        apply_filters( 'aiomatic_ai_reply', $ai_json->result, $query );
        return $ai_json->result;
    }
    else
    {
        try
        {
            $send_json = aiomatic_safe_json_encode( [
                'model' => $model,
                'input' => $aicontent,
                'instruction' => $instruction,
                'temperature' => $temperature,
                'top_p' => $top_p
            ] );
        }
        catch(Exception $e)
        {
            $error = 'Error: Exception in API payload encoding: ' . print_r($e->getMessage(), true);
            $error = apply_filters('aiomatic_modify_ai_error', $error);
            return false;
        }
        if($send_json === false)
        {
            $error = 'Error: Failed to encode API payload: ' . print_r($aicontent, true);
            $error = apply_filters('aiomatic_modify_ai_error', $error);
            return false;
        }
        add_action('http_api_curl', 'aiomatic_add_proxy');
        $xh = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        );
        if (isset($aiomatic_Main_Settings['openai_organization']) && $aiomatic_Main_Settings['openai_organization'] != '')
        {
            $xh['OpenAI-Organization'] = $aiomatic_Main_Settings['openai_organization'];
        }
        $api_call = wp_remote_post(
            'https://api.openai.com/v1/edits',
            array(
                'headers' => $xh,
                'body'        => $send_json,
                'method'      => 'POST',
                'data_format' => 'body',
                'timeout'     => AIOMATIC_DEFAULT_BIG_TIMEOUT,
            )
        );
        remove_action('http_api_curl', 'aiomatic_add_proxy');
        if(is_wp_error( $api_call ))
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after initial edit failure: ' . print_r($api_call, true));
                sleep(pow(2, $retry_count));
                return aiomatic_edit_text($token, $model, $instruction, $aicontent, $temperature, $top_p, $env, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to get initial API response: ' . print_r($api_call, true);
                $error = apply_filters('aiomatic_modify_ai_error', $error);
                return false;
            }
        }
        else
        {
            $result = json_decode( $api_call['body'] );
            if($result === null)
            {
                if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                {
                    aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after decode edit failure: ' . print_r($api_call['body'], true));
                    sleep(pow(2, $retry_count));
                    return aiomatic_edit_text($token, $model, $instruction, $aicontent, $temperature, $top_p, $env, intval($retry_count) + 1, $error);
                }
                else
                {
                    $error = 'Error: Failed to decode initial API response: ' . print_r($api_call, true);
                    $error = apply_filters('aiomatic_modify_ai_error', $error);
                    return false;
                }
            }
            $is_error = false;
            $sleep_time = false;
            if(isset($result->error))
            {
                $result = $result->error;
                $is_error = true;
            }
            if($is_error && isset($result->type))
            {
                if($result->type == 'insufficient_quota')
                {
                    $error = 'Error: You exceeded your OpenAI edits quota limit. To fix this, if you are using a free OpenAI account, you need to add a VISA card to your account, as OpenAI heavily limits free accounts. Please check details here: https://platform.openai.com/docs/guides/rate-limits';
                    $error = apply_filters('aiomatic_modify_ai_error', $error);
                    return false;
                }
                elseif($result->type == 'invalid_request_error')
                {
                    $error = 'Error: Invalid request submitted to the edits API, result: ' . print_r($result, true);
                    $error = apply_filters('aiomatic_modify_ai_error', $error);
                    return false;
                }
                else
                {
                    if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                    {
                        if(isset($result->code) && $result->code == 'rate_limit_exceeded')
                        {
                            $errmessage = $result->message;
                            preg_match_all('#Rate limit reached for.*?in organization.*?Please try again in ([\d.]*?)s#i', $errmessage, $htmlrez);
                            if(isset($htmlrez[1][0]))
                            {
                                $sleep_time = ceil(floatval($htmlrez[1][0]));
                                if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
                                {
                                    aiomatic_log_to_file('Rate limit reached for model: ' . $model . ', sleeping for: ' . $sleep_time . ' seconds');
                                }
                                sleep($sleep_time);
                            }
                        }
                        aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after initial edit failure: ' . print_r($api_call['body'], true));
                        if($sleep_time === false)
                        {
                            sleep(pow(2, $retry_count));
                        }
                        return aiomatic_edit_text($token, $model, $instruction, $aicontent, $temperature, $top_p, $env, intval($retry_count) + 1, $error);
                    }
                    else
                    {
                        $error = 'Error: An error occurred when initially calling OpenAI API: ' . print_r($result, true);
                        $error = apply_filters('aiomatic_modify_ai_error', $error);
                        return false;
                    }
                }
            }
            if(!isset($result->choices[0]->text))
            {
                if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                {
                    aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after choices edit failure: ' . print_r($api_call['body'], true));
                    sleep(pow(2, $retry_count));
                    return aiomatic_edit_text($token, $model, $instruction, $aicontent, $temperature, $top_p, $env, intval($retry_count) + 1, $error);
                }
                else
                {
                    if(isset($result->code) && $result->code == 'content_filter')
                    {
                        $error = 'Error: The response was filtered by our content management policy.';
                        $error = apply_filters('aiomatic_modify_ai_error', $error);
                        return false;
                    }
                    else
                    {
                        $error = 'Error: Choices not found in initial API result: ' . print_r($result, true);
                        $error = apply_filters('aiomatic_modify_ai_error', $error);
                        return false;
                    }
                }
            }
            else
            {
                $result = apply_filters( 'aiomatic_edit_reply_raw', $result, $instruction, $aicontent );
                apply_filters( 'aiomatic_ai_reply', $result->choices[0]->text, $query );
                return $result->choices[0]->text;
            }
        }
    }
    $error = 'Failed to finish API call correctly.';
    $error = apply_filters('aiomatic_modify_ai_error', $error);
    return false;
}

function aiomatic_embeddings_aiomaticapi($token, $model, $input, $retry_count, &$error)
{
    if(aiomatic_is_aiomaticapi_key($token))
    {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        $pargs = array();
        $api_url = 'https://aiomaticapi.com/apis/ai/v1/embeddings/';
        $pargs['apikey'] = trim($token);
        $pargs['input'] = trim($input);
        $pargs['model'] = trim($model);
        $ai_response = aiomatic_get_web_page_api($api_url, $pargs);
        if($ai_response === false)
        {
            $error = 'Error: Failed to get AiomaticAPI response!';
            return false;
        }
        $ai_json = json_decode($ai_response);
        if($ai_json === null)
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after decode embeddings failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_embeddings_aiomaticapi($token, $model, $input, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to decode AiomaticAPI response: ' . $ai_response;
                return false;
            }
        }
        if(isset($ai_json->error))
        {
            if (stristr($ai_json->error, 'Your subscription expired, please renew it.') === false && stristr($ai_json->error, '[RATE LIMITED]') === false && isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after error embeddings failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_embeddings_aiomaticapi($token, $model, $input, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error while processing AI response: ' . $ai_json->error;
                return false;
            }
        }
        if(!isset($ai_json->result))
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') API call after result embeddings failure: ' . print_r($api_url, true));
                sleep(pow(2, $retry_count));
                return aiomatic_embeddings_aiomaticapi($token, $model, $input, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to parse AiomaticAPI response: ' . $ai_response;
                return false;
            }
        }
        if(isset($ai_json->remainingtokens))
        {
            set_transient('aiomaticapi_tokens', $ai_json->remainingtokens, 86400);
        }
        return $ai_json->result;
    }
    $error = 'This function works only for AiomaticAPI keys!';
    return false;
}

function aiomatic_embeddings_azure($token, $model, $input, $retry_count, &$error)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (aiomatic_check_if_azure($aiomatic_Main_Settings)) 
    {
        if (!isset($aiomatic_Main_Settings['azure_endpoint']) || trim($aiomatic_Main_Settings['azure_endpoint']) == '') 
        {
            $error = 'You need to enter an Azure Endpoint for this to work!';
            return false;
        }
        if(in_array($model, AIOMATIC_AZURE_MODELS) === false)
        {
            $error = 'This model is not currently supported by Azure API: ' . $model;
            return false;
        }
        if(aiomatic_is_trained_model($model))
        {
            $error = 'Fine-tuned models are not supported for Azure API';
            return false;
        }
        $localAzureDeployments = array();
        $depl_arr = aiomatic_get_deployments($token);
        if(is_array($depl_arr))
        {
            foreach($depl_arr as $dar)
            {
                if(empty($dar))
                {
                    continue;
                }
                if(is_string($dar))
                {
                    $localAzureDeployments[trim($dar)] = trim($dar);
                }
                else
                {
                    $localAzureDeployments[trim($dar->model)] = trim($dar->id);
                }
            }
        }
        $azureDeployment = '';
        foreach ( $localAzureDeployments as $dmodel => $dname ) 
        {
            if ( $dmodel === str_replace('.', '', $model) || $dmodel === $model ) {
                $azureDeployment = $dname;
                break;
            }
        }
        if ( $azureDeployment == '' ) 
        {
            $new_dep = aiomatic_update_deployments_azure($token);
            if($new_dep !== false)
            {
                $localAzureDeployments = array();
                foreach($new_dep as $dar)
                {
                    if(empty($dar))
                    {
                        continue;
                    }
                    if(is_string($dar))
                    {
                        $localAzureDeployments[trim($dar)] = trim($dar);
                    }
                    else
                    {
                        $localAzureDeployments[trim($dar->model)] = trim($dar->id);
                    }
                }
                foreach ( $localAzureDeployments as $dmodel => $dname ) 
                {
                    if ( $dmodel === str_replace('.', '', $model) || $dmodel === $model ) {
                        $azureDeployment = $dname;
                        break;
                    }
                }
            }
            if ( $azureDeployment == '' ) 
            {
                $error = 'No added Azure deployment found for embeddings model: ' . $model . ' - you need to add this model in your Azure Portal as a Deployment';
                return false;
            }
        }
        if (isset($aiomatic_Main_Settings['azure_api_selector_embeddings']) && $aiomatic_Main_Settings['azure_api_selector_embeddings'] != '' && $aiomatic_Main_Settings['azure_api_selector_embeddings'] != 'default')
        {
            $api_ver = '?api-version=' . $aiomatic_Main_Settings['azure_api_selector_embeddings'];
        }
        else
        {
            $api_ver = AIOMATIC_AZURE_API_VERSION_EMBEDDINGS;
        }
        $apiurl = trailingslashit(trim($aiomatic_Main_Settings['azure_endpoint'])) . 'openai/deployments/' . $azureDeployment . '/embeddings' . $api_ver;
        $base_params = [
            'model' => str_replace('.', '', $model),
            'input' => $input
        ];
        try
        {
            $send_json = aiomatic_safe_json_encode($base_params);
        }
        catch(Exception $e)
        {
            $error = 'Error: Exception in Embeddings Azure API payload encoding: ' . print_r($e->getMessage(), true);
            return false;
        }
        if($send_json === false)
        {
            $error = 'Error: Failed to encode Embeddings Azure API payload: ' . print_r($base_params, true);
            return false;
        }
        add_action('http_api_curl', 'aiomatic_add_proxy');
        $api_call = wp_remote_post(
            $apiurl,
            array(
                'headers' => array( 'Content-Type' => 'application/json', 'api-key' => $token ),
                'body'        => $send_json,
                'method'      => 'POST',
                'data_format' => 'body',
                'timeout'     => AIOMATIC_DEFAULT_BIG_TIMEOUT,
            )
        );
        remove_action('http_api_curl', 'aiomatic_add_proxy');
        if(is_wp_error( $api_call ))
        {
            if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
            {
                aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') Azure embeddings API call after initial failure: ' . print_r($api_call, true));
                sleep(pow(2, $retry_count));
                return aiomatic_embeddings_azure($token, $model, $input, intval($retry_count) + 1, $error);
            }
            else
            {
                $error = 'Error: Failed to get initial Embeddings Azure API response: ' . print_r($api_call, true);
                return false;
            }
        }
        else
        {
            $result = json_decode( $api_call['body'] );
            if($result === null)
            {
                if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                {
                    aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') Embeddings Azure API call after decode failure(4): ' . print_r($api_call['body'], true));
                    sleep(pow(2, $retry_count));
                    return aiomatic_embeddings_azure($token, $model, $input, intval($retry_count) + 1, $error);
                }
                else
                {
                    $error = 'Error: Failed to decode initial Embeddings Azure API response: ' . print_r($api_call, true);
                    return false;
                }
            }
            $is_error = false;
            $sleep_time = false;
            if(isset($result->error))
            {
                $result = $result->error;
                $is_error = true;
            }
            if($is_error && isset($result->type))
            {
                if($result->type == 'insufficient_quota')
                {
                    $error = 'Error: You exceeded your Azure OpenAI quota limit for embeddings, please wait a period for the Azure quota to refill (Embeddings Azure initial call).';
                    return false;
                }
                elseif($result->type == 'invalid_request_error')
                {
                    $error = 'Error: Invalid request submitted to the Embeddings Azure Azure API, result: ' . print_r($result, true);
                    return false;
                }
                else
                {
                    if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                    {
                        if(isset($result->code) && $result->code == 'rate_limit_exceeded')
                        {
                            $errmessage = $result->message;
                            preg_match_all('#Rate limit reached for.*?in organization.*?Please try again in ([\d.]*?)s#i', $errmessage, $htmlrez);
                            if(isset($htmlrez[1][0]))
                            {
                                $sleep_time = ceil(floatval($htmlrez[1][0]));
                                if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
                                {
                                    aiomatic_log_to_file('Rate limit reached for model: ' . $model . ', sleeping for: ' . $sleep_time . ' seconds');
                                }
                                sleep($sleep_time);
                            }
                        }
                        aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') Embeddings Azure Azure API call after type failure: ' . print_r($api_call['body'], true));
                        if($sleep_time === false)
                        {
                            sleep(pow(2, $retry_count));
                        }
                        return aiomatic_embeddings_azure($token, $model, $input, intval($retry_count) + 1, $error);
                    }
                    else
                    {
                        $error = 'Error: An error occurred when initially calling OpenAI Embeddings Azure API: ' . print_r($result, true);
                        return false;
                    }
                }
            }
            if(!isset($result->data))
            {
                delete_option('aiomatic_deployments_list');
                if (isset($aiomatic_Main_Settings['max_retry']) && $aiomatic_Main_Settings['max_retry'] != '' && is_numeric($aiomatic_Main_Settings['max_retry']) && intval($aiomatic_Main_Settings['max_retry']) > $retry_count)
                {
                    aiomatic_log_to_file('Retrying (' . (intval($retry_count) + 1) . ') Embeddings Azure API call after Azure data failure: ' . print_r($api_call['body'], true));
                    sleep(pow(2, $retry_count));
                    return aiomatic_embeddings_azure($token, $model, $input, intval($retry_count) + 1, $error);
                }
                else
                {
                    if(isset($result->code) && $result->code == 'content_filter')
                    {
                        $error = 'Error: The response was filtered by our content management policy.';
                        return false;
                    }
                    else
                    {
                        $error = 'Error: Choices not found in initial Embeddings Azure API result: ' . print_r($result, true);
                        return false;
                    }
                }
            }
            else
            {
                $zempty = array();
                $result->data[0]->usage = (object)$zempty;
                $result->data[0]->usage->total_tokens = count(aiomatic_encode($input));
                return $result->data;
            }
        }
    }
    else
    {
        $error = 'This method is available only when Azure API is used in the plugin!';
        return false;
    }
    $error = 'Unexpected embedding error occured';
    return false;
}

function aiomatic_get_deployments($token)
{
    $deployments_option_value = get_option('aiomatic_deployments_list', false);
	if(!empty($deployments_option_value))
	{
		return $deployments_option_value;
	}
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(isset($aiomatic_Main_Settings['azure_model_deployments']) && is_array($aiomatic_Main_Settings['azure_model_deployments']))
    {
        $localAzureDeployments = array();
        foreach($aiomatic_Main_Settings['azure_model_deployments'] as $modelName => $deploymentName)
        {
            $deplObj = new stdClass();
            $deplObj->model = $modelName;
            $deplObj->id = $deploymentName;
            $localAzureDeployments[] = $deploymentName;
        }
        if(count($localAzureDeployments) > 0)
        {
            return $localAzureDeployments;
        }
    }
    $error = '';
    $deployments = aiomatic_list_deployments_azure($token, $error);
    if(is_array($deployments))
    {
        aiomatic_update_option('aiomatic_deployments_list', $deployments);
        return $deployments;
    }
    else
    {
        aiomatic_log_to_file('Failed to list deployments from Azure, error: ' . $error);
    }
	return false;
}
function aiomatic_update_deployments_azure($token)
{
    $error = '';
    //$deployments = aiomatic_list_deployments_azure($token, $error);
    $deployments = array();
    if(is_array($deployments) && !empty($deployments))
    {
        aiomatic_update_option('aiomatic_deployments_list', $deployments);
        return $deployments;
    }
    else
    {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        if(isset($aiomatic_Main_Settings['azure_model_deployments']) && is_array($aiomatic_Main_Settings['azure_model_deployments']))
        {
            $localAzureDeployments = array();
            foreach($aiomatic_Main_Settings['azure_model_deployments'] as $modelName => $deploymentName)
            {
                $deplObj = new stdClass();
                $deplObj->model = $modelName;
                $deplObj->id = $deploymentName;
                $localAzureDeployments[] = $deplObj;
            }
            if(count($localAzureDeployments) > 0)
            {
                aiomatic_update_option('aiomatic_deployments_list', $localAzureDeployments);
                return $localAzureDeployments;
            }
        }
        aiomatic_log_to_file('Failed to update deployments from Azure, error: ' . $error);
    }
    return false;
}
function aiomatic_list_deployments_azure($token, &$error)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (aiomatic_check_if_azure($aiomatic_Main_Settings)) 
    {
        if (!isset($aiomatic_Main_Settings['azure_endpoint']) || trim($aiomatic_Main_Settings['azure_endpoint']) == '') 
        {
            $error = 'You need to enter an Azure Endpoint for this to work!';
            return false;
        }
        $apiurl = trailingslashit(trim($aiomatic_Main_Settings['azure_endpoint'])) . 'openai/deployments' . AIOMATIC_AZURE_DEPLOYMENT_API_VERSION;
        $base_params = [];
        try
        {
            $send_json = aiomatic_safe_json_encode($base_params);
        }
        catch(Exception $e)
        {
            $error = 'Error: Exception in deployment listing API payload encoding: ' . print_r($e->getMessage(), true);
            return false;
        }
        if($send_json === false)
        {
            $error = 'Error: Failed to encode deployment listing API payload: ' . print_r($base_params, true);
            return false;
        }
        $api_call = wp_remote_get(
            $apiurl,
            array(
                'headers' => array( 'Content-Type' => 'application/json', 'api-key' => $token ),
                'timeout'     => AIOMATIC_DEFAULT_BIG_TIMEOUT,
            )
        );
        if(is_wp_error( $api_call ))
        {
            $error = 'Error: Failed to get initial deployment listing API response: ' . print_r($api_call, true);
            return false;
        }
        else
        {
            $result = json_decode( $api_call['body'] );
            if($result === null)
            {
                $error = 'Error: Failed to decode initial deployment listing API response: ' . print_r($api_call, true);
                return false;
            }
            if(isset($result->error))
            {
                $result = $result->error;
            }
            if(isset($result->type))
            {
                if($result->type == 'insufficient_quota')
                {
                    $error = 'Error: You exceeded your Azure OpenAI quota limit for listings, please wait a period for the quota to refill (deployment listing initial call).';
                    return false;
                }
                elseif($result->type == 'invalid_request_error')
                {
                    $error = 'Error: Invalid request submitted to the deployment listing API, result: ' . print_r($result, true);
                    return false;
                }
                else
                {
                    $error = 'Error: An error occurred when initially calling OpenAI deployment listing API: ' . print_r($result, true);
                    return false;
                }
            }
            if(!isset($result->data))
            {
                if(isset($result->code) && $result->code == 'content_filter')
                {
                    $error = 'Error: The response was filtered by our content management policy.';
                    return false;
                }
                else
                {
                    if(isset($result->code) && $result->code == 'content_filter')
                    {
                        $error = 'Error: The response was filtered by our content management policy.';
                        return false;
                    }
                    else
                    {
                        $error = 'Error: Choices not found in initial deployment listing API result: ' . print_r($result, true);
                        return false;
                    }
                }
            }
            else
            {
                return $result->data;
            }
        }
    }
    else
    {
        $error = 'This method is available only when Azure API is used in the plugin!';
        return false;
    }
    $error = 'Unexpected embedding error occured';
    return false;
}

function aiomatic_get_dalle_image_models()
{
    return AIOMATIC_DALLE_IMAGE_MODELS;
}
function aiomatic_get_stable_image_models()
{
    return AIOMATIC_STABLE_IMAGE_MODELS;
}

function aiomatic_check_video_locally($filename)
{
    $extension = 'mp4';
    $upload_dir = wp_upload_dir();
    global $wp_filesystem;
    if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
        include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
        wp_filesystem($creds);
    }
    if (wp_mkdir_p($upload_dir['path']))
    {
        $file = $upload_dir['path'] . '/' . $filename . '.' . $extension;
        $ret_path = $upload_dir['url'] . '/' . $filename . '.' . $extension;
    }
    else
    {
        $file = $upload_dir['basedir'] . '/' . $filename . '.' . $extension;
        $ret_path = $upload_dir['baseurl'] . '/' . $filename . '.' . $extension;
    }
    if($wp_filesystem->exists($file))
    {
        return $ret_path;
    }
    return false;
}
function aiomatic_copy_audio_stream_locally($stream, $filename, $location = 'local')
{
    $filesize = strlen($stream);
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if($location == 'local')
    {
        $upload_dir = wp_upload_dir();
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
            include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
            wp_filesystem($creds);
        }
        if (wp_mkdir_p($upload_dir['path']))
        {
            $file = $upload_dir['path'] . '/' . $filename;
            $ret_path = $upload_dir['url'] . '/' . $filename;
        }
        else
        {
            $file = $upload_dir['basedir'] . '/' . $filename;
            $ret_path = $upload_dir['baseurl'] . '/' . $filename;
        }
        if($wp_filesystem->exists($file))
        {
            unlink($file);
        }
        
        $ret = $wp_filesystem->put_contents($file, $stream);
        if ($ret === FALSE) {
            return false;
        }
        $wp_filetype = wp_check_filetype( $filename, null );
        $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return array($ret_path, $file);
    }
    elseif($location == 'amazon')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['bucket_name']) || trim($aiomatic_Main_Settings['bucket_name']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 bucket_name for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['s3_user']) || trim($aiomatic_Main_Settings['s3_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 s3_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['s3_pass']) || trim($aiomatic_Main_Settings['s3_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 s3_pass for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['bucket_region']) || trim($aiomatic_Main_Settings['bucket_region']) == '')
        {
            $aiomatic_Main_Settings['bucket_region'] = 'eu-central-1';
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['s3_user']), 'secret' => trim($aiomatic_Main_Settings['s3_pass']));
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => trim($aiomatic_Main_Settings['bucket_region']),
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['drive_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['drive_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['bucket_name']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $stream,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL'], '');
            }
            else
            {
                aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
                return false;
            }
        }
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        } 
    }
    elseif($location == 'wasabi')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_bucket']) || trim($aiomatic_Main_Settings['wasabi_bucket']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 wasabi_bucket for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_region']) || trim($aiomatic_Main_Settings['wasabi_region']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 wasabi_region for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_user']) || trim($aiomatic_Main_Settings['wasabi_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 wasabi_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_pass']) || trim($aiomatic_Main_Settings['wasabi_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 wasabi_pass for this to work!');
            return false;
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['wasabi_user']), 'secret' => trim($aiomatic_Main_Settings['wasabi_pass']));
            $s3 = new S3Client([
                'endpoint' => "https://" . trim($aiomatic_Main_Settings['wasabi_bucket']) . ".s3." . trim($aiomatic_Main_Settings['wasabi_region']) . ".wasabisys.com/",
                'bucket_endpoint' => true,
                'version' => 'latest',
                'region'  => trim($aiomatic_Main_Settings['wasabi_region']),
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['wasabi_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['wasabi_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['wasabi_bucket']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $stream,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL'], '');
            }
            else
            {
                aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
                return false;
            }
        }
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        } 
    }
    elseif($location == 'cloudflare')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_bucket']) || trim($aiomatic_Main_Settings['cloud_bucket']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 cloud_bucket for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_account']) || trim($aiomatic_Main_Settings['cloud_account']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 cloud_account for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_user']) || trim($aiomatic_Main_Settings['cloud_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 cloud_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_pass']) || trim($aiomatic_Main_Settings['cloud_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 cloud_pass for this to work!');
            return false;
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['cloud_user']), 'secret' => trim($aiomatic_Main_Settings['cloud_pass']));
            $s3 = new S3Client([
                'endpoint' => "https://" . trim($aiomatic_Main_Settings['cloud_account']) . ".r2.cloudflarestorage.com",
                'bucket_endpoint' => true,
                'version' => 'latest',
                'region' => 'us-east-1',
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['cloud_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['cloud_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['cloud_bucket']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $stream,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL'], '');
            }
            else
            {
                aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
                return false;
            }
        }
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        } 
    }
    elseif($location == 'digital')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_endpoint']) || trim($aiomatic_Main_Settings['digital_endpoint']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 digital_endpoint for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_user']) || trim($aiomatic_Main_Settings['digital_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 digital_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_pass']) || trim($aiomatic_Main_Settings['digital_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 digital_pass for this to work!');
            return false;
        }
        $bucket_name = '';
        preg_match_all('#https:\/\/([^.]*?)\.(?:[^.]*?)\.digitaloceanspaces\.com#i', trim($aiomatic_Main_Settings['digital_endpoint']), $zmatches);
        if(isset($zmatches[1][0]))
        {
            $bucket_name = $zmatches[1][0];
        }
        else
        {
            aiomatic_log_to_file('Failed to parse Digital Ocean Spaces URL: ' . trim($aiomatic_Main_Settings['digital_endpoint']));
            return false;
        }
        $endpoint_plain_url = preg_replace('#https?:\/\/([^.]*?\.)([^.]*?)\.digitaloceanspaces\.com#i', 'https://$2.digitaloceanspaces.com', trim($aiomatic_Main_Settings['digital_endpoint']));
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['digital_user']), 'secret' => trim($aiomatic_Main_Settings['digital_pass']));
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => 'us-east-1',
                'endpoint' => $endpoint_plain_url,
                'use_path_style_endpoint' => false,
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['digital_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['digital_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $obj_arr = [
                'Bucket' => trim($bucket_name),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $stream,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL'], '');
            }
            else
            {
                aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
                return false;
            }
        }
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        } 
    }
    else
    {
        return false;
    }
}
function aiomatic_copy_video_locally($image_url, $filename, $location = 'local')
{
    $extension = 'mp4';
    $image_data = aiomatic_get_web_page($image_url);
    if ($image_data === FALSE || strpos($image_data, '<Message>Access Denied</Message>') !== FALSE || strpos($image_data, '<title>Just a moment...</title>') !== FALSE || strpos($image_data, '<html') !== FALSE) 
    {
        return false;
    }
    $filesize = strlen($image_data);
    if($location === 'local')
    {
        $upload_dir = wp_upload_dir();
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
            include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
            wp_filesystem($creds);
        }
        if (wp_mkdir_p($upload_dir['path'] . '/videos'))
        {
            $file = $upload_dir['path'] . '/videos/' . $filename . '.' . $extension;
            $ret_path = $upload_dir['url'] . '/videos/' . $filename . '.' . $extension;
        }
        else
        {
            $file = $upload_dir['basedir'] . '/videos/' . $filename . '.' . $extension;
            $ret_path = $upload_dir['baseurl'] . '/videos/' . $filename . '.' . $extension;
        }
        if($wp_filesystem->exists($file))
        {
            unlink($file);
        }
        
        $ret = $wp_filesystem->put_contents($file, $image_data);
        if ($ret === FALSE) {
            return false;
        }
        $wp_filetype = wp_check_filetype( $filename . '.' . $extension, null );
        $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename . '.' . $extension ),
        'post_content' => '',
        'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return array($ret_path, $file);
    }
    elseif($location == 'amazon')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['bucket_name']) || trim($aiomatic_Main_Settings['bucket_name']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 bucket_name for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['s3_user']) || trim($aiomatic_Main_Settings['s3_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 s3_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['s3_pass']) || trim($aiomatic_Main_Settings['s3_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 s3_pass for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['bucket_region']) || trim($aiomatic_Main_Settings['bucket_region']) == '')
        {
            $aiomatic_Main_Settings['bucket_region'] = 'eu-central-1';
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['s3_user']), 'secret' => trim($aiomatic_Main_Settings['s3_pass']));
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => trim($aiomatic_Main_Settings['bucket_region']),
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['drive_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['drive_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['bucket_name']),
                'Key'    => $s3_remote_path . $filename . '.' . $extension,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL'], '');
            }
            else
            {
                aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
                return false;
            }
        }
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        } 
    }
    elseif($location == 'wasabi')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_bucket']) || trim($aiomatic_Main_Settings['wasabi_bucket']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 wasabi_bucket for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_region']) || trim($aiomatic_Main_Settings['wasabi_region']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 wasabi_region for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_user']) || trim($aiomatic_Main_Settings['wasabi_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 wasabi_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_pass']) || trim($aiomatic_Main_Settings['wasabi_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 wasabi_pass for this to work!');
            return false;
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['wasabi_user']), 'secret' => trim($aiomatic_Main_Settings['wasabi_pass']));
            $s3 = new S3Client([
                'endpoint' => "https://" . trim($aiomatic_Main_Settings['wasabi_bucket']) . ".s3." . trim($aiomatic_Main_Settings['wasabi_region']) . ".wasabisys.com/",
                'bucket_endpoint' => true,
                'version' => 'latest',
                'region'  => trim($aiomatic_Main_Settings['wasabi_region']),
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['wasabi_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['wasabi_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['wasabi_bucket']),
                'Key'    => $s3_remote_path . $filename . '.' . $extension,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL'], '');
            }
            else
            {
                aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
                return false;
            }
        }
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        } 
    }
    elseif($location == 'cloudflare')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_bucket']) || trim($aiomatic_Main_Settings['cloud_bucket']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 cloud_bucket for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_account']) || trim($aiomatic_Main_Settings['cloud_account']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 cloud_account for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_user']) || trim($aiomatic_Main_Settings['cloud_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 cloud_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_pass']) || trim($aiomatic_Main_Settings['cloud_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 cloud_pass for this to work!');
            return false;
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['cloud_user']), 'secret' => trim($aiomatic_Main_Settings['cloud_pass']));
            $s3 = new S3Client([
                'endpoint' => "https://" . trim($aiomatic_Main_Settings['cloud_account']) . ".r2.cloudflarestorage.com",
                'bucket_endpoint' => true,
                'version' => 'latest',
                'region' => 'us-east-1',
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['cloud_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['cloud_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['cloud_bucket']),
                'Key'    => $s3_remote_path . $filename . '.' . $extension,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL'], '');
            }
            else
            {
                aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
                return false;
            }
        }
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        } 
    }
    elseif($location == 'digital')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_endpoint']) || trim($aiomatic_Main_Settings['digital_endpoint']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 digital_endpoint for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_user']) || trim($aiomatic_Main_Settings['digital_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 digital_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_pass']) || trim($aiomatic_Main_Settings['digital_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 digital_pass for this to work!');
            return false;
        }
        $bucket_name = '';
        preg_match_all('#https:\/\/([^.]*?)\.(?:[^.]*?)\.digitaloceanspaces\.com#i', trim($aiomatic_Main_Settings['digital_endpoint']), $zmatches);
        if(isset($zmatches[1][0]))
        {
            $bucket_name = $zmatches[1][0];
        }
        else
        {
            aiomatic_log_to_file('Failed to parse Digital Ocean Spaces URL: ' . trim($aiomatic_Main_Settings['digital_endpoint']));
            return false;
        }
        $endpoint_plain_url = preg_replace('#https?:\/\/([^.]*?\.)([^.]*?)\.digitaloceanspaces\.com#i', 'https://$2.digitaloceanspaces.com', trim($aiomatic_Main_Settings['digital_endpoint']));
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['digital_user']), 'secret' => trim($aiomatic_Main_Settings['digital_pass']));
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => 'us-east-1',
                'endpoint' => $endpoint_plain_url,
                'use_path_style_endpoint' => false,
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['digital_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['digital_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $obj_arr = [
                'Bucket' => trim($bucket_name),
                'Key'    => $s3_remote_path . $filename . '.' . $extension,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL'], '');
            }
            else
            {
                aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
                return false;
            }
        }
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        } 
    }
    else
    {
        return false;
    }
}
function aiomatic_copy_image_locally($image_url, $copy_locally = '', $del_existing = false)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(!empty($copy_locally))
    {
        $aiomatic_Main_Settings['copy_locally'] = $copy_locally;
    }
    if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
    {
        aiomatic_log_to_file('Copying image (' . $aiomatic_Main_Settings['copy_locally'] . '): ' . $image_url);
    }
    if (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'on')
    {
        if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
        {
            aiomatic_log_to_file('Copying image locally: ' . $image_url);
        }
        $upload_dir = wp_upload_dir();
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
            include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
            wp_filesystem($creds);
        }
        if(substr( $image_url, 0, 10 ) === "data:image")
        {
            $data = explode(',', $image_url);
            if(isset($data[1]))
            {
                $image_data = base64_decode($data[1]);
                if($image_data === FALSE)
                {
                    return false;
                }
            }
            else
            {
                return false;
            }
            preg_match('{data:image/(.*?);}', $image_url, $ex_matches);
            if(isset($ex_matches[1]))
            {
                $image_url = 'image.' . $ex_matches[1];
            }
            else
            {
                $image_url = 'image.jpg';
            }
        }
        else
        {
            $image_data = aiomatic_get_web_page($image_url);
            if ($image_data === FALSE || strpos($image_data, '<Message>Access Denied</Message>') !== FALSE || strpos($image_data, '<title>Just a moment...</title>') !== FALSE || strpos($image_data, '<html') !== FALSE) 
            {
                if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
                {
                    aiomatic_log_to_file('Failed to download image: ' . $image_url);
                }
                return false;
            }
        }
        $image_data_temp = aiomatic_string_to_string_compress($image_data);
        if($image_data_temp !== false)
        {
            $image_data = $image_data_temp;
        }
        $filename = basename($image_url);
        $filename = explode("?", $filename);
        $filename = $filename[0];
        $filename = urlencode($filename);
        $filename = aiomatic_limitStringTo($filename, 10);
        $filename = str_replace('%', '-', $filename);
        $filename = str_replace('#', '-', $filename);
        $filename = str_replace('&', '-', $filename);
        $filename = str_replace('{', '-', $filename);
        $filename = str_replace('}', '-', $filename);
        $filename = str_replace('\\', '-', $filename);
        $filename = str_replace('<', '-', $filename);
        $filename = str_replace('>', '-', $filename);
        $filename = str_replace('*', '-', $filename);
        $filename = str_replace('/', '-', $filename);
        $filename = str_replace('$', '-', $filename);
        $filename = str_replace('\'', '-', $filename);
        $filename = str_replace('"', '-', $filename);
        $filename = str_replace(':', '-', $filename);
        $filename = str_replace('@', '-', $filename);
        $filename = str_replace('+', '-', $filename);
        $filename = str_replace('|', '-', $filename);
        $filename = str_replace('=', '-', $filename);
        $filename = str_replace('`', '-', $filename);
        $file_parts = pathinfo($filename);
        if(!isset($file_parts['extension']))
        {
            $file_parts['extension'] = '';
        }
        switch($file_parts['extension'])
        {
            case "":
            if(!aiomatic_endsWith($filename, '.jpg'))
                $filename .= '.jpg';
            break;
            case NULL:
            if(!aiomatic_endsWith($filename, '.jpg'))
                $filename .= '.jpg';
            break;
        }
        if (wp_mkdir_p($upload_dir['path']))
        {
            $file = $upload_dir['path'] . '/' . $filename;
            $ret_path = $upload_dir['url'] . '/' . $filename;
        }
        else
        {
            $file = $upload_dir['basedir'] . '/' . $filename;
            $ret_path = $upload_dir['baseurl'] . '/' . $filename;
        }
        if($wp_filesystem->exists($file))
        {
            if($del_existing)
            {
                unlink($file);
            }
            else
            {
                if(empty($file_parts['extension']))
                {
                    $file_parts['extension'] = 'jpg';
                }
                $unid = uniqid();
                $file .= $unid . '.' . $file_parts['extension'];
                $ret_path .= $unid . '.' . $file_parts['extension'];
            }
        }
        
        $ret = $wp_filesystem->put_contents($file, $image_data);
        if ($ret === FALSE) {
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Failed to save image locally: ' . $image_url . ' - to: ' . $file);
            }
            return false;
        }
        $wp_filetype = wp_check_filetype( $filename, null );
        $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return array($ret_path, $file);
    }
    elseif (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'amazon')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['bucket_name']) || trim($aiomatic_Main_Settings['bucket_name']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 bucket_name for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['s3_user']) || trim($aiomatic_Main_Settings['s3_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 s3_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['s3_pass']) || trim($aiomatic_Main_Settings['s3_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 s3_pass for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['bucket_region']) || trim($aiomatic_Main_Settings['bucket_region']) == '')
        {
            $aiomatic_Main_Settings['bucket_region'] = 'eu-central-1';
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['s3_user']), 'secret' => trim($aiomatic_Main_Settings['s3_pass']));
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => trim($aiomatic_Main_Settings['bucket_region']),
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['drive_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['drive_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            if(substr( $image_url, 0, 10 ) === "data:image")
            {
                $data = explode(',', $image_url);
                if(isset($data[1]))
                {
                    $image_data = base64_decode($data[1]);
                    if($image_data === FALSE)
                    {
                        aiomatic_log_to_file('Failed to decode image: ' . $image_url);
                        return false;
                    }
                }
                else
                {
                    aiomatic_log_to_file('Failed to parse image: ' . $image_url);
                    return false;
                }
            }
            else
            {
                $image_data = aiomatic_get_web_page($image_url);
                if ($image_data === FALSE || strpos($image_data, '<Message>Access Denied</Message>') !== FALSE || strpos($image_data, '<title>Just a moment...</title>') !== FALSE || strpos($image_data, '<html') !== FALSE) 
                {
                    aiomatic_log_to_file('Failed to download image: ' . $image_url);
                    return false;
                }
            }
            $image_data_temp = aiomatic_string_to_string_compress($image_data);
            if($image_data_temp !== false)
            {
                $image_data = $image_data_temp;
            }
            $filesize = strlen($image_data);
            $filename = basename($image_url);
            $filename = explode("?", $filename);
            $filename = $filename[0];
            $filename = urlencode($filename);
            $filename = str_replace('%', '-', $filename);
            $filename = str_replace('#', '-', $filename);
            $filename = str_replace('&', '-', $filename);
            $filename = str_replace('{', '-', $filename);
            $filename = str_replace('}', '-', $filename);
            $filename = str_replace('\\', '-', $filename);
            $filename = str_replace('<', '-', $filename);
            $filename = str_replace('>', '-', $filename);
            $filename = str_replace('*', '-', $filename);
            $filename = str_replace('/', '-', $filename);
            $filename = str_replace('$', '-', $filename);
            $filename = str_replace('\'', '-', $filename);
            $filename = str_replace('"', '-', $filename);
            $filename = str_replace(':', '-', $filename);
            $filename = str_replace('@', '-', $filename);
            $filename = str_replace('+', '-', $filename);
            $filename = str_replace('|', '-', $filename);
            $filename = str_replace('=', '-', $filename);
            $filename = str_replace('`', '-', $filename);
            $file_parts = pathinfo($filename);
            if(!isset($file_parts['extension']))
            {
                $file_parts['extension'] = '';
            }
            switch($file_parts['extension'])
            {
                case "":
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
                case NULL:
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
            }
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['bucket_name']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL']);
            }
            aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
            return false;
        } 
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage());
            return false;
        }
    }
    elseif (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'wasabi')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_bucket']) || trim($aiomatic_Main_Settings['wasabi_bucket']) == '')
        {
            aiomatic_log_to_file('You need to enter a Wasabi wasabi_bucket for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_region']) || trim($aiomatic_Main_Settings['wasabi_region']) == '')
        {
            aiomatic_log_to_file('You need to enter a Wasabi wasabi_region for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_user']) || trim($aiomatic_Main_Settings['wasabi_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Wasabi wasabi_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_pass']) || trim($aiomatic_Main_Settings['wasabi_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Wasabi wasabi_pass for this to work!');
            return false;
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['wasabi_user']), 'secret' => trim($aiomatic_Main_Settings['wasabi_pass']));
            $s3 = new S3Client([
                'endpoint' => "https://" . trim($aiomatic_Main_Settings['wasabi_bucket']) . ".s3." . trim($aiomatic_Main_Settings['wasabi_region']) . ".wasabisys.com/",
                'bucket_endpoint' => true,
                'version' => 'latest',
                'region'  => trim($aiomatic_Main_Settings['wasabi_region']),
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Wasabi API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['wasabi_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['wasabi_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            if(substr( $image_url, 0, 10 ) === "data:image")
            {
                $data = explode(',', $image_url);
                if(isset($data[1]))
                {
                    $image_data = base64_decode($data[1]);
                    if($image_data === FALSE)
                    {
                        aiomatic_log_to_file('Failed to decode image: ' . $image_url);
                        return false;
                    }
                }
                else
                {
                    aiomatic_log_to_file('Failed to parse image: ' . $image_url);
                    return false;
                }
            }
            else
            {
                $image_data = aiomatic_get_web_page($image_url);
                if ($image_data === FALSE || strpos($image_data, '<Message>Access Denied</Message>') !== FALSE || strpos($image_data, '<title>Just a moment...</title>') !== FALSE || strpos($image_data, '<html') !== FALSE) 
                {
                    aiomatic_log_to_file('Failed to download image: ' . $image_url);
                    return false;
                }
            }
            $image_data_temp = aiomatic_string_to_string_compress($image_data);
            if($image_data_temp !== false)
            {
                $image_data = $image_data_temp;
            }
            $filesize = strlen($image_data);
            $filename = basename($image_url);
            $filename = explode("?", $filename);
            $filename = $filename[0];
            $filename = urlencode($filename);
            $filename = str_replace('%', '-', $filename);
            $filename = str_replace('#', '-', $filename);
            $filename = str_replace('&', '-', $filename);
            $filename = str_replace('{', '-', $filename);
            $filename = str_replace('}', '-', $filename);
            $filename = str_replace('\\', '-', $filename);
            $filename = str_replace('<', '-', $filename);
            $filename = str_replace('>', '-', $filename);
            $filename = str_replace('*', '-', $filename);
            $filename = str_replace('/', '-', $filename);
            $filename = str_replace('$', '-', $filename);
            $filename = str_replace('\'', '-', $filename);
            $filename = str_replace('"', '-', $filename);
            $filename = str_replace(':', '-', $filename);
            $filename = str_replace('@', '-', $filename);
            $filename = str_replace('+', '-', $filename);
            $filename = str_replace('|', '-', $filename);
            $filename = str_replace('=', '-', $filename);
            $filename = str_replace('`', '-', $filename);
            $file_parts = pathinfo($filename);
            if(!isset($file_parts['extension']))
            {
                $file_parts['extension'] = '';
            }
            switch($file_parts['extension'])
            {
                case "":
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
                case NULL:
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
            }
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['wasabi_bucket']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL']);
            }
            aiomatic_log_to_file("Failed to decode Wasabi API response: " . print_r($awsret, true));
            return false;
        } 
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Wasabi: " . $e->getMessage());
            return false;
        }
    }
    elseif (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'cloudflare')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_bucket']) || trim($aiomatic_Main_Settings['cloud_bucket']) == '')
        {
            aiomatic_log_to_file('You need to enter a CloudFlare R2 cloud_bucket for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_account']) || trim($aiomatic_Main_Settings['cloud_account']) == '')
        {
            aiomatic_log_to_file('You need to enter a CloudFlare cloud_account for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_user']) || trim($aiomatic_Main_Settings['cloud_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a CloudFlare cloud_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_pass']) || trim($aiomatic_Main_Settings['cloud_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a CloudFlare cloud_pass for this to work!');
            return false;
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['cloud_user']), 'secret' => trim($aiomatic_Main_Settings['cloud_pass']));
            $s3 = new S3Client([
                'endpoint' => "https://" . trim($aiomatic_Main_Settings['cloud_account']) . ".r2.cloudflarestorage.com",
                'bucket_endpoint' => true,
                'version' => 'latest',
                'region' => 'us-east-1',
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize CloudFlare API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['cloud_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['cloud_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            if(substr( $image_url, 0, 10 ) === "data:image")
            {
                $data = explode(',', $image_url);
                if(isset($data[1]))
                {
                    $image_data = base64_decode($data[1]);
                    if($image_data === FALSE)
                    {
                        aiomatic_log_to_file('Failed to decode image: ' . $image_url);
                        return false;
                    }
                }
                else
                {
                    aiomatic_log_to_file('Failed to parse image: ' . $image_url);
                    return false;
                }
            }
            else
            {
                $image_data = aiomatic_get_web_page($image_url);
                if ($image_data === FALSE || strpos($image_data, '<Message>Access Denied</Message>') !== FALSE || strpos($image_data, '<title>Just a moment...</title>') !== FALSE || strpos($image_data, '<html') !== FALSE) 
                {
                    aiomatic_log_to_file('Failed to download image: ' . $image_url);
                    return false;
                }
            }
            $image_data_temp = aiomatic_string_to_string_compress($image_data);
            if($image_data_temp !== false)
            {
                $image_data = $image_data_temp;
            }
            $filesize = strlen($image_data);
            $filename = basename($image_url);
            $filename = explode("?", $filename);
            $filename = $filename[0];
            $filename = urlencode($filename);
            $filename = str_replace('%', '-', $filename);
            $filename = str_replace('#', '-', $filename);
            $filename = str_replace('&', '-', $filename);
            $filename = str_replace('{', '-', $filename);
            $filename = str_replace('}', '-', $filename);
            $filename = str_replace('\\', '-', $filename);
            $filename = str_replace('<', '-', $filename);
            $filename = str_replace('>', '-', $filename);
            $filename = str_replace('*', '-', $filename);
            $filename = str_replace('/', '-', $filename);
            $filename = str_replace('$', '-', $filename);
            $filename = str_replace('\'', '-', $filename);
            $filename = str_replace('"', '-', $filename);
            $filename = str_replace(':', '-', $filename);
            $filename = str_replace('@', '-', $filename);
            $filename = str_replace('+', '-', $filename);
            $filename = str_replace('|', '-', $filename);
            $filename = str_replace('=', '-', $filename);
            $filename = str_replace('`', '-', $filename);
            $file_parts = pathinfo($filename);
            if(!isset($file_parts['extension']))
            {
                $file_parts['extension'] = '';
            }
            switch($file_parts['extension'])
            {
                case "":
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
                case NULL:
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
            }
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['cloud_bucket']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL']);
            }
            aiomatic_log_to_file("Failed to decode CloudFlare API response: " . print_r($awsret, true));
            return false;
        } 
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to CloudFlare: " . $e->getMessage());
            return false;
        }
    }
    elseif (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'digital')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_endpoint']) || trim($aiomatic_Main_Settings['digital_endpoint']) == '')
        {
            aiomatic_log_to_file('You need to enter a Digital Ocean digital_endpoint for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_user']) || trim($aiomatic_Main_Settings['digital_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Digital Ocean digital_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_pass']) || trim($aiomatic_Main_Settings['digital_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Digital Ocean digital_pass for this to work!');
            return false;
        }
        $bucket_name = '';
        preg_match_all('#https:\/\/([^.]*?)\.(?:[^.]*?)\.digitaloceanspaces\.com#i', trim($aiomatic_Main_Settings['digital_endpoint']), $zmatches);
        if(isset($zmatches[1][0]))
        {
            $bucket_name = $zmatches[1][0];
        }
        else
        {
            aiomatic_log_to_file('Failed to parse Digital Ocean Spaces URL: ' . trim($aiomatic_Main_Settings['digital_endpoint']));
            return false;
        }
        $endpoint_plain_url = preg_replace('#https?:\/\/([^.]*?\.)([^.]*?)\.digitaloceanspaces\.com#i', 'https://$2.digitaloceanspaces.com', trim($aiomatic_Main_Settings['digital_endpoint']));
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['digital_user']), 'secret' => trim($aiomatic_Main_Settings['digital_pass']));
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => 'us-east-1',
                'endpoint' => $endpoint_plain_url,
                'use_path_style_endpoint' => false,
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Digital Ocean Spaces API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['digital_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['digital_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            if(substr( $image_url, 0, 10 ) === "data:image")
            {
                $data = explode(',', $image_url);
                if(isset($data[1]))
                {
                    $image_data = base64_decode($data[1]);
                    if($image_data === FALSE)
                    {
                        aiomatic_log_to_file('Failed to decode image: ' . $image_url);
                        return false;
                    }
                }
                else
                {
                    aiomatic_log_to_file('Failed to parse image: ' . $image_url);
                    return false;
                }
            }
            else
            {
                $image_data = aiomatic_get_web_page($image_url);
                if ($image_data === FALSE || strpos($image_data, '<Message>Access Denied</Message>') !== FALSE || strpos($image_data, '<title>Just a moment...</title>') !== FALSE || strpos($image_data, '<html') !== FALSE) 
                {
                    aiomatic_log_to_file('Failed to download image: ' . $image_url);
                    return false;
                }
            }
            $image_data_temp = aiomatic_string_to_string_compress($image_data);
            if($image_data_temp !== false)
            {
                $image_data = $image_data_temp;
            }
            $filesize = strlen($image_data);
            $filename = basename($image_url);
            $filename = explode("?", $filename);
            $filename = $filename[0];
            $filename = urlencode($filename);
            $filename = str_replace('%', '-', $filename);
            $filename = str_replace('#', '-', $filename);
            $filename = str_replace('&', '-', $filename);
            $filename = str_replace('{', '-', $filename);
            $filename = str_replace('}', '-', $filename);
            $filename = str_replace('\\', '-', $filename);
            $filename = str_replace('<', '-', $filename);
            $filename = str_replace('>', '-', $filename);
            $filename = str_replace('*', '-', $filename);
            $filename = str_replace('/', '-', $filename);
            $filename = str_replace('$', '-', $filename);
            $filename = str_replace('\'', '-', $filename);
            $filename = str_replace('"', '-', $filename);
            $filename = str_replace(':', '-', $filename);
            $filename = str_replace('@', '-', $filename);
            $filename = str_replace('+', '-', $filename);
            $filename = str_replace('|', '-', $filename);
            $filename = str_replace('=', '-', $filename);
            $filename = str_replace('`', '-', $filename);
            $file_parts = pathinfo($filename);
            if(!isset($file_parts['extension']))
            {
                $file_parts['extension'] = '';
            }
            switch($file_parts['extension'])
            {
                case "":
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
                case NULL:
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
            }
            $obj_arr = [
                'Bucket' => trim($bucket_name),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL']);
            }
            aiomatic_log_to_file("Failed to decode Digital Ocean Spaces API response: " . print_r($awsret, true));
            return false;
        } 
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file " . $image_url . " to Digital Ocean Spaces: " . $e->getMessage());
            return false;
        }
    }
    else
    {
        return false;
    }
}

function aiomatic_copy_file_locally($image_data, $filename, $copy_locally = '', $del_existing = false)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(!empty($copy_locally))
    {
        $aiomatic_Main_Settings['copy_locally'] = $copy_locally;
    }
    if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
    {
        aiomatic_log_to_file('Copying file (' . $aiomatic_Main_Settings['copy_locally'] . ')');
    }
    if (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'on')
    {
        $upload_dir = wp_upload_dir();
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
            include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
            wp_filesystem($creds);
        }
        $file_parts = pathinfo($filename);
        if(!isset($file_parts['extension']))
        {
            $file_parts['extension'] = '';
        }
        if (wp_mkdir_p($upload_dir['path']))
        {
            $file = $upload_dir['path'] . '/' . $filename;
            $ret_path = $upload_dir['url'] . '/' . $filename;
        }
        else
        {
            $file = $upload_dir['basedir'] . '/' . $filename;
            $ret_path = $upload_dir['baseurl'] . '/' . $filename;
        }
        if($wp_filesystem->exists($file))
        {
            if($del_existing)
            {
                unlink($file);
            }
            else
            {
                $unid = uniqid();
                if(!empty($file_parts['extension']))
                {
                    $file .= $unid . '.' . $file_parts['extension'];
                    $ret_path .= $unid . '.' . $file_parts['extension'];
                }
                else
                {
                    $file .= $unid;
                    $ret_path .= $unid;
                }
            }
        }
        
        $ret = $wp_filesystem->put_contents($file, $image_data);
        if ($ret === FALSE) {
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Failed to save file locally to: ' . $file);
            }
            return false;
        }
        $wp_filetype = wp_check_filetype( $filename, null );
        $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return array($ret_path, $file);
    }
    elseif (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'amazon')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['bucket_name']) || trim($aiomatic_Main_Settings['bucket_name']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 bucket_name for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['s3_user']) || trim($aiomatic_Main_Settings['s3_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 s3_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['s3_pass']) || trim($aiomatic_Main_Settings['s3_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Amazon S3 s3_pass for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['bucket_region']) || trim($aiomatic_Main_Settings['bucket_region']) == '')
        {
            $aiomatic_Main_Settings['bucket_region'] = 'eu-central-1';
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['s3_user']), 'secret' => trim($aiomatic_Main_Settings['s3_pass']));
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => trim($aiomatic_Main_Settings['bucket_region']),
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Amazon S3 API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['drive_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['drive_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $filesize = strlen($image_data);
            $file_parts = pathinfo($filename);
            if(!isset($file_parts['extension']))
            {
                $file_parts['extension'] = '';
            }
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['bucket_name']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL']);
            }
            aiomatic_log_to_file("Failed to decode Amazon S3 API response: " . print_r($awsret, true));
            return false;
        } 
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file to Amazon S3: " . $e->getMessage());
            return false;
        }
    }
    elseif (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'wasabi')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_bucket']) || trim($aiomatic_Main_Settings['wasabi_bucket']) == '')
        {
            aiomatic_log_to_file('You need to enter a Wasabi wasabi_bucket for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_region']) || trim($aiomatic_Main_Settings['wasabi_region']) == '')
        {
            aiomatic_log_to_file('You need to enter a Wasabi wasabi_region for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_user']) || trim($aiomatic_Main_Settings['wasabi_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Wasabi wasabi_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['wasabi_pass']) || trim($aiomatic_Main_Settings['wasabi_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Wasabi wasabi_pass for this to work!');
            return false;
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['wasabi_user']), 'secret' => trim($aiomatic_Main_Settings['wasabi_pass']));
            $s3 = new S3Client([
                'endpoint' => "https://" . trim($aiomatic_Main_Settings['wasabi_bucket']) . ".s3." . trim($aiomatic_Main_Settings['wasabi_region']) . ".wasabisys.com/",
                'bucket_endpoint' => true,
                'version' => 'latest',
                'region'  => trim($aiomatic_Main_Settings['wasabi_region']),
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Wasabi API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['wasabi_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['wasabi_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $filesize = strlen($image_data);
            $file_parts = pathinfo($filename);
            if(!isset($file_parts['extension']))
            {
                $file_parts['extension'] = '';
            }
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['wasabi_bucket']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL']);
            }
            aiomatic_log_to_file("Failed to decode Wasabi API response: " . print_r($awsret, true));
            return false;
        } 
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file to Wasabi: " . $e->getMessage());
            return false;
        }
    }
    elseif (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'cloudflare')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_bucket']) || trim($aiomatic_Main_Settings['cloud_bucket']) == '')
        {
            aiomatic_log_to_file('You need to enter a CloudFlare R2 cloud_bucket for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_account']) || trim($aiomatic_Main_Settings['cloud_account']) == '')
        {
            aiomatic_log_to_file('You need to enter a CloudFlare cloud_account for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_user']) || trim($aiomatic_Main_Settings['cloud_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a CloudFlare cloud_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['cloud_pass']) || trim($aiomatic_Main_Settings['cloud_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a CloudFlare cloud_pass for this to work!');
            return false;
        }
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['cloud_user']), 'secret' => trim($aiomatic_Main_Settings['cloud_pass']));
            $s3 = new S3Client([
                'endpoint' => "https://" . trim($aiomatic_Main_Settings['cloud_account']) . ".r2.cloudflarestorage.com",
                'bucket_endpoint' => true,
                'version' => 'latest',
                'region' => 'us-east-1',
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize CloudFlare API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['cloud_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['cloud_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $filesize = strlen($image_data);
            $file_parts = pathinfo($filename);
            if(!isset($file_parts['extension']))
            {
                $file_parts['extension'] = '';
            }
            $obj_arr = [
                'Bucket' => trim($aiomatic_Main_Settings['cloud_bucket']),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL']);
            }
            aiomatic_log_to_file("Failed to decode CloudFlare API response: " . print_r($awsret, true));
            return false;
        } 
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file to CloudFlare: " . $e->getMessage());
            return false;
        }
    }
    elseif (isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] == 'digital')
    {
        if(!function_exists('is_plugin_active'))
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
        {
            aiomatic_log_to_file('You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_endpoint']) || trim($aiomatic_Main_Settings['digital_endpoint']) == '')
        {
            aiomatic_log_to_file('You need to enter a Digital Ocean digital_endpoint for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_user']) || trim($aiomatic_Main_Settings['digital_user']) == '')
        {
            aiomatic_log_to_file('You need to enter a Digital Ocean digital_user for this to work!');
            return false;
        }
        if (!isset($aiomatic_Main_Settings['digital_pass']) || trim($aiomatic_Main_Settings['digital_pass']) == '')
        {
            aiomatic_log_to_file('You need to enter a Digital Ocean digital_pass for this to work!');
            return false;
        }
        $bucket_name = '';
        preg_match_all('#https:\/\/([^.]*?)\.(?:[^.]*?)\.digitaloceanspaces\.com#i', trim($aiomatic_Main_Settings['digital_endpoint']), $zmatches);
        if(isset($zmatches[1][0]))
        {
            $bucket_name = $zmatches[1][0];
        }
        else
        {
            aiomatic_log_to_file('Failed to parse Digital Ocean Spaces URL: ' . trim($aiomatic_Main_Settings['digital_endpoint']));
            return false;
        }
        $endpoint_plain_url = preg_replace('#https?:\/\/([^.]*?\.)([^.]*?)\.digitaloceanspaces\.com#i', 'https://$2.digitaloceanspaces.com', trim($aiomatic_Main_Settings['digital_endpoint']));
        try
        {
            $credentials = array('key' => trim($aiomatic_Main_Settings['digital_user']), 'secret' => trim($aiomatic_Main_Settings['digital_pass']));
            $s3 = new S3Client([
                'version' => 'latest',
                'region'  => 'us-east-1',
                'endpoint' => $endpoint_plain_url,
                'use_path_style_endpoint' => false,
                'credentials' => $credentials
            ]);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to initialize Digital Ocean Spaces API: ' . $e->getMessage());
            return false;
        }
        if (trim($aiomatic_Main_Settings['digital_directory']) != '') {
            $s3_remote_path = trim(trim($aiomatic_Main_Settings['digital_directory']), '/');
            $s3_remote_path = trailingslashit($s3_remote_path);
        }
        else
        {
            $s3_remote_path = '';
        }
        try 
        {
            $filesize = strlen($image_data);
            $file_parts = pathinfo($filename);
            if(!isset($file_parts['extension']))
            {
                $file_parts['extension'] = '';
            }
            $obj_arr = [
                'Bucket' => trim($bucket_name),
                'Key'    => $s3_remote_path . $filename,
                'Body'   => $image_data,
                'Content-Length' => $filesize,
                'ContentLength' => $filesize
            ];
            $obj_arr['ACL'] = 'public-read';
            $awsret = $s3->putObject($obj_arr);
            if(isset($awsret['ObjectURL']))
            {
                return array($awsret['ObjectURL']);
            }
            aiomatic_log_to_file("Failed to decode Digital Ocean Spaces API response: " . print_r($awsret, true));
            return false;
        } 
        catch (Exception $e) 
        {
            aiomatic_log_to_file("There was an error uploading the file to Digital Ocean Spaces: " . $e->getMessage());
            return false;
        }
    }
    else
    {
        return false;
    }
}

function aiomatic_file_get_contents_advanced($url, $headers = '', $referrer = 'self', $user_agent = false)
{
    $content = false;
    if (function_exists('curl_init')) 
    {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        $max_redirects = 10;
        $ch = curl_init();
        if($ch !== false)
        {
            curl_setopt($ch, CURLOPT_URL, $url);
            if (strtolower($referrer) == 'self') {
                curl_setopt($ch, CURLOPT_REFERER, $url);
            } elseif (strlen($referrer)) {
                curl_setopt($ch, CURLOPT_REFERER, $referrer);
            }
            if ($user_agent) {
                curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            } 
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $headers = trim($headers);
            if (strlen($headers)) {
                $headers_array = explode(PHP_EOL, $headers);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_array);
            }
            if (isset($aiomatic_Main_Settings['proxy_url']) && $aiomatic_Main_Settings['proxy_url'] != '' && $aiomatic_Main_Settings['proxy_url'] != 'disable' && $aiomatic_Main_Settings['proxy_url'] != 'disabled') {
                $prx = explode(',', $aiomatic_Main_Settings['proxy_url']);
                $randomness = array_rand($prx);
                curl_setopt( $ch, CURLOPT_PROXY, trim($prx[$randomness]));
                if (isset($aiomatic_Main_Settings['proxy_auth']) && $aiomatic_Main_Settings['proxy_auth'] != '') 
                {
                    $prx_auth = explode(',', $aiomatic_Main_Settings['proxy_auth']);
                    if(isset($prx_auth[$randomness]) && trim($prx_auth[$randomness]) != '')
                    {
                        curl_setopt( $ch, CURLOPT_PROXYUSERPWD, trim($prx_auth[$randomness]));
                    }
                }
            }
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $max_redirects);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $content = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code != 200) {
                $content = false;
            }
            curl_close($ch);
        }
    }
    if (!isset($content) || $content === false) {
        stream_context_set_default(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false), 'http' => array('method' => 'HEAD', 'timeout' => 10, 'user_agent' => $user_agent)));
        $content = file_get_contents($url);
    }
    return $content;
}
function aiomatic_get_random_image_google($keyword, $min_width = 0, $min_height = 0, $chance = '', &$added_img_list = array(), &$full_result_list = array())
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(isset($aiomatic_Main_Settings['image_pool']) && $aiomatic_Main_Settings['image_pool'] != '')
    {
        $max_res = intval($aiomatic_Main_Settings['image_pool']);
    }
    else
    {
        $max_res = 4;
    }
    if($chance != '' && is_numeric($chance))
    {
        $chance = intval($chance);
        if(mt_rand(0, 99) >= $chance)
        {
            return '';
        }
    }
    if(isset($aiomatic_Main_Settings['image_query_translate_en']) && $aiomatic_Main_Settings['image_query_translate_en'] == 'on')
    {
        $text_trans = aiomatic_translate_stability($keyword);
        if($text_trans != $keyword && !empty($text_trans))
        {
            aiomatic_log_to_file('Google Images query translated from: "' . $keyword . '" to: "' . $text_trans . '"');
            $keyword = $text_trans;
        }
    }
    $gimageurl = 'https://www.google.com/search?q=' . urlencode($keyword . ' -site:depositphotos.com -site:123rf.com') . '&tbm=isch&tbs=il:cl&sa=X';
    $res = aiomatic_file_get_contents_advanced($gimageurl, '', 'self', 'Mozilla/5.0 (Windows NT 10.0;WOW64;rv:97.0) Gecko/20000101 Firefox/97.0/3871tuT2p1u-81');
    preg_match_all('/\["([\w%-\.\/:\?&=]+\.jpg|\.jpeg|\.gif|\.png|\.bmp|\.wbmp|\.webm|\.xbm)",\d+,\d+\]/i', $res, $matches);
    $items = $matches[0];
    if (count($items)) 
    {
        foreach($items as $it)
        {
            preg_match('#\["(.*?)",(.*?),(.*?)\]#', $it, $xmatches);
            if (count($xmatches) == 4 && ($min_width > 0 || $min_width <= $xmatches[3]) && ($min_height > 0 || $min_height <= $xmatches[2])) 
            {
                $full_result_list[] = $xmatches[1];
            }
        }
        $items = array_slice($items, 0, $max_res, true);
        if(!isset($aiomatic_Main_Settings['random_results_order']) || $aiomatic_Main_Settings['random_results_order'] != 'on')
        {
            shuffle($items);
        }
        foreach ($items as $item) {
            preg_match('#\["(.*?)",(.*?),(.*?)\]#', $item, $matches);
            if (count($matches) == 4 && ($min_width > 0 || $min_width <= $matches[3]) && ($min_height > 0 || $min_height <= $matches[2])) 
            {
                if(!in_array($matches[1], $added_img_list))
                {
                    $added_img_list[] = $matches[1];
                    return $matches[1];
                }
            }
        }
    }
    return '';
}
function aiomatic_get_random_image_google_serp($keyword, $chance = '', &$added_img_list = array(), &$full_result_list = array())
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(isset($aiomatic_Main_Settings['image_pool']) && $aiomatic_Main_Settings['image_pool'] != '')
    {
        $max_res = intval($aiomatic_Main_Settings['image_pool']);
    }
    else
    {
        $max_res = 4;
    }
    if($chance != '' && is_numeric($chance))
    {
        $chance = intval($chance);
        if(mt_rand(0, 99) >= $chance)
        {
            return '';
        }
    }
    if(isset($aiomatic_Main_Settings['image_query_translate_en']) && $aiomatic_Main_Settings['image_query_translate_en'] == 'on')
    {
        $text_trans = aiomatic_translate_stability($keyword);
        if($text_trans != $keyword && !empty($text_trans))
        {
            aiomatic_log_to_file('Google Images query translated from: "' . $keyword . '" to: "' . $text_trans . '"');
            $keyword = $text_trans;
        }
    }
    if (isset($aiomatic_Main_Settings['google_search_api']) && trim($aiomatic_Main_Settings['google_search_api']) != '') 
    {
        if (isset($aiomatic_Main_Settings['google_search_cx']) && trim($aiomatic_Main_Settings['google_search_cx']) != '') 
        {
            $items = array();
            $is_ok = true;
            $page_number = 0;
            $result_number = 10;
            while($is_ok && count($items) < $max_res)
            {
                if($page_number == 0)
                {
                    $first = 0;
                }
                else
                {
                    $first = ($page_number * $result_number) + 1;
                }
                if($first > 91)
                {
                    break;
                }
                $internet_params = array(
                    'q'   => urlencode( $keyword ),
                    'cx'  => trim($aiomatic_Main_Settings['google_search_cx']),
                    'key' => trim($aiomatic_Main_Settings['google_search_api']),
                    'num' => $result_number,
                    'start' => $first,
                    'searchType' => 'image',
                    'rights' => 'cc_publicdomain'
                );
                $feed_uri = add_query_arg( $internet_params, 'https://www.googleapis.com/customsearch/v1' );
                $responser = aiomatic_get_web_page($feed_uri);
                if ($responser === FALSE) 
                {
                    $is_ok = false;
                }
                else
                {
                    $json_resp = json_decode($responser);
                    if ($json_resp === null) 
                    {
                        $is_ok = false;
                    }
                    else
                    {
                        if (isset($json_resp->items)) 
                        {
                            $items_temp = $json_resp->items;
                            if(!isset($aiomatic_Main_Settings['random_results_order']) || $aiomatic_Main_Settings['random_results_order'] != 'on')
                            {
                                shuffle($items_temp);
                            }
                            foreach($items_temp as $jitem)
                            {
                                $items[] = $jitem->link;
                                if(count($items) >= $max_res)
                                {
                                    break;
                                }
                            }
                            $page_number++;
                        }
                        else
                        {
                            $is_ok = false;
                        }
                    }
                }
            }
            if(count($items) > 0)
            {
                foreach($items as $it)
                {
                    $full_result_list[] = $it;
                }
                foreach($items as $it)
                {
                    if(!in_array($it, $added_img_list))
                    {
                        $added_img_list[] = $it;
                        return $it;
                    }
                }
            }
        }
    }
    return '';
}
$aiomatic_fatal = false;
function aiomatic_clear_flag_at_shutdown($param, $type)
{
    $error = error_get_last();
    if ($error !== null && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR || $error['type'] === E_USER_ERROR) && $GLOBALS['aiomatic_fatal'] === false) {
        $GLOBALS['aiomatic_fatal'] = true;
        $running = array();
        aiomatic_update_option('aiomatic_running_list', $running);
        aiomatic_log_to_file('[FATAL] Exit error: ' . $error['message'] . ', file: ' . $error['file'] . ', line: ' . $error['line'] . ' - rule ID: ' . $param . '!');
        aiomatic_clearFromList($param, $type);
    }
    else
    {
        aiomatic_clearFromList($param, $type);
    }
}
add_filter('the_title', 'aiomatic_add_affiliate_keyword_title');
function aiomatic_add_affiliate_keyword_title($content)
{
    global $post;
    $rules  = get_option('aiomatic_keyword_list');
    if(!is_array($rules))
    {
       $rules = array();
    }
    if (!empty($rules)) {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        if(isset($aiomatic_Main_Settings['kw_skip_ids']) && $aiomatic_Main_Settings['kw_skip_ids'] != '')
        {
            $skip_ids = explode(',', $aiomatic_Main_Settings['kw_skip_ids']);
            $skip_ids = array_map('trim', $skip_ids);
            if(isset($post->ID) && is_numeric($post->ID) && in_array($post->ID, $skip_ids))
            {
                return $content;
            }
        }
        if(isset($aiomatic_Main_Settings['partial_kws']) && $aiomatic_Main_Settings['partial_kws'] == 'on')
        {
            $word_boundry = '';
        }
        else
        {
            $word_boundry = '\b';
        }
        if(isset($aiomatic_Main_Settings['kws_case']) && $aiomatic_Main_Settings['kws_case'] == 'on')
        {
            $add_case = '';
        }
        else
        {
            $add_case = 'i';
        }
        if(isset($aiomatic_Main_Settings['no_new_tab_kw']) && $aiomatic_Main_Settings['no_new_tab_kw'] == 'on')
        {
            $add_blank = '';
        }
        else
        {
            $add_blank = ' target="_blank"';
        }
        foreach ($rules as $request => $value) {
            if(isset($value[2]) && $value[2] == 'content')
            {
                continue;
            }
            if (is_array($value) && isset($value[1]) && $value[1] != '') 
            {
                $repl = $value[1];
            } else {
                $repl = $request;
            }
            if (isset($value[3]) && $value[3] != '') 
            {
                $max = intval($value[3]);
            }
            else
            {
                $max = -1;
            }
            if (isset($value[0]) && !empty($value[0])) 
            {
                $content = preg_replace('\'(?!((<.*?)|(<a.*?)))(' . $word_boundry . preg_quote($request, '\'') . $word_boundry . ')(?!(([^<>]*?)>)|([^>]*?<\/a>))\'' . $add_case, '<a href="' . esc_url_raw($value[0]) . '"' . $add_blank . '>' . esc_html($repl) . '</a>', $content, $max);
            } 
            else 
            {
                $content = preg_replace('\'(?!((<.*?)|(<a.*?)))(' . $word_boundry . preg_quote($request, '\'') . $word_boundry . ')(?!(([^<>]*?)>)|([^>]*?<\/a>))\'' . $add_case, esc_html($repl), $content, $max);
            }
        }
    }
    return $content;
}
add_filter('the_content', 'aiomatic_add_affiliate_keyword');
add_filter('the_excerpt', 'aiomatic_add_affiliate_keyword');
function aiomatic_add_affiliate_keyword($content)
{
    global $post;
    $rules  = get_option('aiomatic_keyword_list');
    if(!is_array($rules))
    {
       $rules = array();
    }
    if (!empty($rules)) {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        if(isset($aiomatic_Main_Settings['kw_skip_ids']) && $aiomatic_Main_Settings['kw_skip_ids'] != '')
        {
            $skip_ids = explode(',', $aiomatic_Main_Settings['kw_skip_ids']);
            $skip_ids = array_map('trim', $skip_ids);
            if(isset($post->ID) && is_numeric($post->ID) && in_array($post->ID, $skip_ids))
            {
                return $content;
            }
        }
        if(isset($aiomatic_Main_Settings['partial_kws']) && $aiomatic_Main_Settings['partial_kws'] == 'on')
        {
            $word_boundry = '';
        }
        else
        {
            $word_boundry = '\b';
        }
        if(isset($aiomatic_Main_Settings['kws_case']) && $aiomatic_Main_Settings['kws_case'] == 'on')
        {
            $add_case = '';
        }
        else
        {
            $add_case = 'i';
        }
        if(isset($aiomatic_Main_Settings['no_new_tab_kw']) && $aiomatic_Main_Settings['no_new_tab_kw'] == 'on')
        {
            $add_blank = '';
        }
        else
        {
            $add_blank = ' target="_blank"';
        }
        foreach ($rules as $request => $value) {
            if(isset($value[2]) && $value[2] == 'title')
            {
                continue;
            }
            if (is_array($value) && isset($value[1]) && $value[1] != '') {
                $repl = $value[1];
            } else {
                $repl = $request;
            }
            if (isset($value[3]) && $value[3] != '') {
                $max = intval($value[3]);
            }
            else
            {
                $max = -1;
            }
            if (isset($value[0]) && !empty($value[0])) 
            {
                $content1 = preg_replace('\'(?!((<.*?)|(<a.*?)))(' . $word_boundry . preg_quote($request, '\'') . $word_boundry . ')(?!(([^<>]*?)>)|([^>]*?<\/a>))\'' . $add_case, '<a href="' . esc_url_raw($value[0]) . '"' . $add_blank . '>' . esc_html($repl) . '</a>', $content, $max);
                if($content1 !== null)
                {
                    $content = $content1;
                }
            } else {
                $content1 = preg_replace('\'(?!((<.*?)|(<a.*?)))(' . $word_boundry . preg_quote($request, '\'') . $word_boundry . ')(?!(([^<>]*?)>)|([^>]*?<\/a>))\'' . $add_case, esc_html($repl), $content, $max);
                if($content1 !== null)
                {
                    $content = $content1;
                }
            }
        }
    }
    return $content;
}

function aiomatic_get_free_image($aiomatic_Main_Settings, $query_words, &$img_attr, $res_cnt = 3, $no_copy = false, &$added_img_list = array(), $rand_arr = array(), &$full_result_list = array())
{
    if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
    {
        aiomatic_log_to_file('Searching for a royalty free image for keyword: ' . $query_words);
    }
    $original_url = '';
    if(empty($rand_arr))
    {
        $rand_arr = get_option('aiomatic_image_cards_order', array());
    }
    if(empty($rand_arr))
    {
        if(isset($aiomatic_Main_Settings['pixabay_api']) && $aiomatic_Main_Settings['pixabay_api'] != '')
        {
            $rand_arr[] = 'pixabay';
        }
        if(isset($aiomatic_Main_Settings['flickr_api']) && $aiomatic_Main_Settings['flickr_api'] !== '')
        {
            $rand_arr[] = 'flickr';
        }
        if(isset($aiomatic_Main_Settings['pexels_api']) && $aiomatic_Main_Settings['pexels_api'] !== '')
        {
            $rand_arr[] = 'pexels';
        }
        if(isset($aiomatic_Main_Settings['pixabay_scrape']) && $aiomatic_Main_Settings['pixabay_scrape'] == 'on')
        {
            $rand_arr[] = 'pixabayscrape';
        }
        if(isset($aiomatic_Main_Settings['unsplash_key']) && $aiomatic_Main_Settings['unsplash_key'] != '')
        {
            $rand_arr[] = 'unsplash';
        }
        if(isset($aiomatic_Main_Settings['google_images']) && $aiomatic_Main_Settings['google_images'] == 'on')
        {
            $rand_arr[] = 'google';
        }
        if(isset($aiomatic_Main_Settings['google_images_api']) && $aiomatic_Main_Settings['google_images_api'] == 'on')
        {
            $rand_arr[] = 'googleapi';
        }
    }
    $rez = false;
    while(($rez === false || $rez === '') && count($rand_arr) > 0)
    {
        if(!isset($aiomatic_Main_Settings['random_image_sources']) || $aiomatic_Main_Settings['random_image_sources'] != 'on')
        {
            $rand = array_rand($rand_arr);
        }
        else
        {
            $rand = array_key_first($rand_arr);
        }
        if($rand_arr[$rand] == 'pixabay')
        {
            unset($rand_arr[$rand]);
            if(isset($aiomatic_Main_Settings['img_ss']) && $aiomatic_Main_Settings['img_ss'] == 'on')
            {
                $img_ss = '1';
            }
            else
            {
                $img_ss = '0';
            }
            if(isset($aiomatic_Main_Settings['img_editor']) && $aiomatic_Main_Settings['img_editor'] == 'on')
            {
                $img_editor = '1';
            }
            else
            {
                $img_editor = '0';
            }
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Searching Pixabay...');
            }
            $rez = aiomatic_get_pixabay_image($aiomatic_Main_Settings['pixabay_api'], $query_words, $aiomatic_Main_Settings['img_language'], $aiomatic_Main_Settings['imgtype'], $aiomatic_Main_Settings['scrapeimg_orientation'], $aiomatic_Main_Settings['img_order'], $aiomatic_Main_Settings['img_cat'], $aiomatic_Main_Settings['img_mwidth'], $aiomatic_Main_Settings['img_width'], $img_ss, $img_editor, $original_url, $res_cnt, $added_img_list, $full_result_list);
            if($rez !== false && $rez !== '')
            {
                $img_attr = str_replace('%%image_source_name%%', 'Pixabay', $img_attr);
                $img_attr = str_replace('%%image_source_url%%', $original_url, $img_attr);
                $img_attr = str_replace('%%image_source_website%%', 'https://pixabay.com/', $img_attr);
            }
        }
        elseif($rand_arr[$rand] == 'morguefile')
        {
            unset($rand_arr[$rand]);
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Searching Morguefile...');
            }
            $rez = aiomatic_get_morguefile_image($aiomatic_Main_Settings['morguefile_api'], $aiomatic_Main_Settings['morguefile_secret'], $query_words, $original_url, $added_img_list, $full_result_list);
            if($rez !== false && $rez !== '')
            {
                $img_attr = str_replace('%%image_source_name%%', 'MorgueFile', $img_attr);
                $img_attr = str_replace('%%image_source_url%%', 'https://morguefile.com/', $img_attr);
                $img_attr = str_replace('%%image_source_website%%', 'https://morguefile.com/', $img_attr);
            }
        }
        elseif($rand_arr[$rand] == 'flickr')
        {
            unset($rand_arr[$rand]);
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Searching Flickr...');
            }
            $rez = aiomatic_get_flickr_image($aiomatic_Main_Settings, $query_words, $original_url, $res_cnt, $added_img_list, $full_result_list);
            if($rez !== false && $rez !== '')
            {
                $img_attr = str_replace('%%image_source_name%%', 'Flickr', $img_attr);
                $img_attr = str_replace('%%image_source_url%%', $original_url, $img_attr);
                $img_attr = str_replace('%%image_source_website%%', 'https://www.flickr.com/', $img_attr);
            }
        }
        elseif($rand_arr[$rand] == 'pexels')
        {
            unset($rand_arr[$rand]);
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Searching Pexels...');
            }
            $rez = aiomatic_get_pexels_image($aiomatic_Main_Settings, $query_words, $original_url, $res_cnt, $added_img_list, $full_result_list);
            if($rez !== false && $rez !== '')
            {
                $img_attr = str_replace('%%image_source_name%%', 'Pexels', $img_attr);
                $img_attr = str_replace('%%image_source_url%%', $original_url, $img_attr);
                $img_attr = str_replace('%%image_source_website%%', 'https://www.pexels.com/', $img_attr);
            }
        }
        elseif($rand_arr[$rand] == 'pixabayscrape')
        {
            unset($rand_arr[$rand]);
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Searching Pixabay Scraping...');
            }
            $rez = aiomatic_scrape_pixabay_image($aiomatic_Main_Settings, $query_words, $original_url, $added_img_list, $full_result_list);
            if($rez !== false && $rez !== '')
            {
                $img_attr = str_replace('%%image_source_name%%', 'Pixabay', $img_attr);
                $img_attr = str_replace('%%image_source_url%%', $original_url, $img_attr);
                $img_attr = str_replace('%%image_source_website%%', 'https://pixabay.com/', $img_attr);
            }
        }
        elseif($rand_arr[$rand] == 'unsplash')
        {
            unset($rand_arr[$rand]);
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Searching Unsplash...');
            }
            $rez = aiomatic_scrape_unsplash_image($query_words, $original_url, $added_img_list, $full_result_list);
            if($rez !== false && $rez !== '')
            {
                $img_attr = str_replace('%%image_source_name%%', 'Unsplash', $img_attr);
                $img_attr = str_replace('%%image_source_url%%', $original_url, $img_attr);
                $img_attr = str_replace('%%image_source_website%%', 'https://unsplash.com/', $img_attr);
            }
        }
        elseif($rand_arr[$rand] == 'google')
        {
            unset($rand_arr[$rand]);
            $original_url = 'https://google.com/';
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Searching Google Images...');
            }
            $rez = aiomatic_get_random_image_google($query_words, 0, 0, '', $added_img_list, $full_result_list);
            if($rez !== false && $rez !== '')
            {
                $img_attr = str_replace('%%image_source_name%%', 'Google Images', $img_attr);
                $img_attr = str_replace('%%image_source_url%%', $original_url, $img_attr);
                $img_attr = str_replace('%%image_source_website%%', 'https://google.com/', $img_attr);
            }
        }
        elseif($rand_arr[$rand] == 'googleapi')
        {
            unset($rand_arr[$rand]);
            $original_url = 'https://google.com/';
            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
            {
                aiomatic_log_to_file('Searching Google SERP API Images...');
            }
            $rez = aiomatic_get_random_image_google_serp($query_words, '', $added_img_list, $full_result_list);
            if($rez !== false && $rez !== '')
            {
                $img_attr = str_replace('%%image_source_name%%', 'Google Search Images', $img_attr);
                $img_attr = str_replace('%%image_source_url%%', $original_url, $img_attr);
                $img_attr = str_replace('%%image_source_website%%', 'https://google.com/', $img_attr);
            }
        }
        else
        {
            aiomatic_log_to_file('Unrecognized free file source: ' . $rand_arr[$rand]);
            unset($rand_arr[$rand]);
        }
    }
    $img_attr = str_replace('%%image_source_name%%', '', $img_attr);
    $img_attr = str_replace('%%image_source_url%%', '', $img_attr);
    $img_attr = str_replace('%%image_source_website%%', '', $img_attr);
    if($rez !== false && $rez !== '')
    {
        if($no_copy !== true)
        {
            if(isset($aiomatic_Main_Settings['copy_locally']) && $aiomatic_Main_Settings['copy_locally'] != 'disabled')
            {
                $localpath = aiomatic_copy_image_locally($rez);
                if($localpath !== false)
                {
                    $rez = $localpath[0];
                }
            }
        }
    }
    return $rez;
}
function aiomatic_scrape_pixabay_image($aiomatic_Main_Settings, $query, &$original_url, &$added_img_list = array(), &$full_result_list = array())
{
    if(isset($aiomatic_Main_Settings['image_pool']) && $aiomatic_Main_Settings['image_pool'] != '')
    {
        $max_res = intval($aiomatic_Main_Settings['image_pool']);
    }
    else
    {
        $max_res = 4;
    }
    $original_url = 'https://pixabay.com';
    $featured_image = '';
    if(isset($aiomatic_Main_Settings['image_query_translate_en']) && $aiomatic_Main_Settings['image_query_translate_en'] == 'on')
    {
        $text_trans = aiomatic_translate_stability($query);
        if($text_trans != $query && !empty($text_trans))
        {
            aiomatic_log_to_file('Pixabay Scraper query translated from: "' . $query . '" to: "' . $text_trans . '"');
            $query = $text_trans;
        }
    }
    $feed_uri = 'https://pixabay.com/photos/search/' . urlencode($query) . '/';
    if($aiomatic_Main_Settings['scrapeimgtype'] != 'all')
    {
        if(strstr($feed_uri, '?'))
        {
            $feed_uri .= '&image_type=' . $aiomatic_Main_Settings['scrapeimgtype'];
        }
        else
        {
            $feed_uri .= '?image_type=' . $aiomatic_Main_Settings['scrapeimgtype'];
        }
    }
    if($aiomatic_Main_Settings['scrapeimg_orientation'] != '')
    {
        if(strstr($feed_uri, '?'))
        {
            $feed_uri .= '&orientation=' . $aiomatic_Main_Settings['scrapeimg_orientation'];
        }
        else
        {
            $feed_uri .= '?orientation=' . $aiomatic_Main_Settings['scrapeimg_orientation'];
        }
    }
    if($aiomatic_Main_Settings['scrapeimg_order'] != '' && $aiomatic_Main_Settings['scrapeimg_order'] != 'any')
    {
        if(strstr($feed_uri, '?'))
        {
            $feed_uri .= '&order=' . $aiomatic_Main_Settings['scrapeimg_order'];
        }
        else
        {
            $feed_uri .= '?order=' . $aiomatic_Main_Settings['scrapeimg_order'];
        }
    }
    if($aiomatic_Main_Settings['scrapeimg_cat'] != '')
    {
        if(strstr($feed_uri, '?'))
        {
            $feed_uri .= '&category=' . $aiomatic_Main_Settings['scrapeimg_cat'];
        }
        else
        {
            $feed_uri .= '?category=' . $aiomatic_Main_Settings['scrapeimg_cat'];
        }
    }
    if($aiomatic_Main_Settings['scrapeimg_height'] != '')
    {
        if(strstr($feed_uri, '?'))
        {
            $feed_uri .= '&min_height=' . $aiomatic_Main_Settings['scrapeimg_height'];
        }
        else
        {
            $feed_uri .= '?min_height=' . $aiomatic_Main_Settings['scrapeimg_height'];
        }
    }
    if($aiomatic_Main_Settings['scrapeimg_width'] != '')
    {
        if(strstr($feed_uri, '?'))
        {
            $feed_uri .= '&min_width=' . $aiomatic_Main_Settings['scrapeimg_width'];
        }
        else
        {
            $feed_uri .= '?min_width=' . $aiomatic_Main_Settings['scrapeimg_width'];
        }
    }
    $exec = aiomatic_get_web_page_from_search($feed_uri);
    if ($exec !== FALSE) 
    {
        preg_match_all('/<a href="([^"]+?)".+?(?:data-lazy|src)="([^"]+?\.jpg|png)"/i', $exec, $matches);
        if (!empty($matches[2])) 
        {
            $p = array_combine($matches[1], $matches[2]);
            if(count($p) > 0)
            {
                foreach($p as $im)
                {
                    $full_result_list[] = $im;
                }
                $p = array_slice($p, 0, $max_res, true);
                if(!isset($aiomatic_Main_Settings['random_results_order']) || $aiomatic_Main_Settings['random_results_order'] != 'on')
                {
                    shuffle($p);
                }
                foreach ($p as $key => $val) 
                {
                    if(!in_array($val, $added_img_list))
                    {
                        $added_img_list[] = $val;
                        $featured_image = $val;
                        if(!is_numeric($key))
                        {
                            if(substr($key, 0, 4) !== "http")
                            {
                                $key = 'https://pixabay.com' . $key;
                            }
                            $original_url = $key;
                        }
                        else
                        {
                            $original_url = 'https://pixabay.com';
                        }
                        break;
                    }
                }
            }
        }
    }
    else
    {
        aiomatic_log_to_file('Error while getting api url: ' . $feed_uri);
        return false;
    }
    return $featured_image;
}
function aiomatic_scrape_unsplash_image($query, &$original_url, &$added_img_list = array(), &$full_result_list = array())
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(isset($aiomatic_Main_Settings['image_pool']) && $aiomatic_Main_Settings['image_pool'] != '')
    {
        $max_res = intval($aiomatic_Main_Settings['image_pool']);
    }
    else
    {
        $max_res = 4;
    }
    if(!isset($aiomatic_Main_Settings['unsplash_key']) || trim($aiomatic_Main_Settings['unsplash_key']) == '')
    {
        aiomatic_log_to_file('You need to enter an Unsplash API key for this to work');
        return false;
    }
    if($query == '')
    {
        aiomatic_log_to_file('Empty queries are not allowed for Unsplash.');
        return false;
    }
    if(isset($aiomatic_Main_Settings['image_query_translate_en']) && $aiomatic_Main_Settings['image_query_translate_en'] == 'on')
    {
        $text_trans = aiomatic_translate_stability($query);
        if($text_trans != $query && !empty($text_trans))
        {
            aiomatic_log_to_file('Unsplash query translated from: "' . $query . '" to: "' . $text_trans . '"');
            $query = $text_trans;
        }
    }
    $original_url = 'https://unsplash.com/';
    $page = 1;
    $perPage = 30;
    $orderBy = 'relevant';
    $collections = '';
    $contentFilter = 'low';
    $color = '';
    $orientation = '';
    $feed_uri = "https://api.unsplash.com/search/photos";
    $params = [
        'query' => $query,
        'page' => $page,
        'per_page' => $perPage,
        'order_by' => $orderBy,
        'collections' => $collections,
        'content_filter' => $contentFilter,
        'client_id' => trim($aiomatic_Main_Settings['unsplash_key'])
    ];
    if(!empty($orientation))
    {
        $params['orientation'] = $orientation;
    }
    if(!empty($color))
    {
        $params['color'] = $color;
    }
    $featured_image = '';
    $queryUrl = $feed_uri . '?' . http_build_query($params);
    $ch               = curl_init();
    if ($ch === FALSE) {
        aiomatic_log_to_file('Failed to init curl for Unsplash!');
        return false;
    }
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);
    if (isset($aiomatic_Main_Settings['max_timeout']) && $aiomatic_Main_Settings['max_timeout'] != '')
    {
        $ztime = intval($aiomatic_Main_Settings['max_timeout']);
    }
    else
    {
        $ztime = 300;
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, $ztime);
    curl_setopt($ch, CURLOPT_URL, $queryUrl);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $exec = curl_exec($ch);
    curl_close($ch);
    $items = json_decode ( $exec, true );
    if(!isset($items['results']))
    {
        aiomatic_log_to_file('Failed to find photo node in Unsplash response URI: ' . $queryUrl);
        return false;
    }
    if(count($items['results']) == 0)
    {
        return $featured_image;
    }
    $x = 0;
    foreach($items['results'] as $photox)
    {
        if(isset($photox['urls']['raw']))
        {
            $full_result_list[] = $photox['urls']['raw'];
        }
    }
    $items['results'] = array_slice($items['results'], 0, $max_res, true);
    if(!isset($aiomatic_Main_Settings['random_results_order']) || $aiomatic_Main_Settings['random_results_order'] != 'on')
    {
        shuffle($items['results']);
    }
    while($featured_image == '' && isset($items['results'][$x]))
    {
        $item = $items['results'][$x];
        if(isset($item['urls']['raw']))
        {
            if(!in_array($item['urls']['raw'], $added_img_list))
            {
                $featured_image = $item['urls']['raw'];
                $added_img_list[] = $featured_image;
            }
        }
        if($featured_image != '' && isset($item['links']['html']))
        {
            $original_url = $item['links']['html'];
        }
        $x++;
    }
    return $featured_image;
}
function aiomatic_get_pexels_image($aiomatic_Main_Settings, $query, &$original_url, $max, &$added_img_list = array(), &$full_result_list = array())
{
    if(isset($aiomatic_Main_Settings['image_pool']) && $aiomatic_Main_Settings['image_pool'] != '')
    {
        $max_res = intval($aiomatic_Main_Settings['image_pool']);
    }
    else
    {
        $max_res = 4;
    }
    $original_url = 'https://pexels.com';
    $featured_image = '';
    if(isset($aiomatic_Main_Settings['image_query_translate_en']) && $aiomatic_Main_Settings['image_query_translate_en'] == 'on')
    {
        $text_trans = aiomatic_translate_stability($query);
        if($text_trans != $query && !empty($text_trans))
        {
            aiomatic_log_to_file('Pexels Query translated from: "' . $query . '" to: "' . $text_trans . '"');
            $query = $text_trans;
        }
    }
    $feed_uri = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=' . $max;
     
    {
        $ch               = curl_init();
        if ($ch === FALSE) {
            aiomatic_log_to_file('Failed to init curl for flickr!');
            return false;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . trim($aiomatic_Main_Settings['pexels_api'])));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        if (isset($aiomatic_Main_Settings['max_timeout']) && $aiomatic_Main_Settings['max_timeout'] != '')
        {
            $ztime = intval($aiomatic_Main_Settings['max_timeout']);
        }
        else
        {
            $ztime = 300;
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $ztime);
        curl_setopt($ch, CURLOPT_URL, $feed_uri);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $exec = curl_exec($ch);
        if (stristr($exec, 'photos') === FALSE) {
            aiomatic_log_to_file('Unrecognized Pexels API response URI: ' . $feed_uri . ' error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        $items = json_decode ( $exec, true );
        if(!isset($items['photos']))
        {
            aiomatic_log_to_file('Failed to find photo node in Pexels response URI: ' . $feed_uri);
            return false;
        }
        if(count($items['photos']) == 0)
        {
            return $featured_image;
        }
        $x = 0;
        foreach($items['photos'] as $photox)
        {
            if(isset($photox['src']['large']))
            {
                $full_result_list[] = $photox['src']['large'];
            }
            elseif(isset($photox['src']['medium']))
            {
                $full_result_list[] = $photox['src']['medium'];
            }
            elseif(isset($photox['src']['small']))
            {
                $full_result_list[] = $photox['src']['small'];
            }
            elseif(isset($photox['src']['portrait']))
            {
                $full_result_list[] = $photox['src']['portrait'];
            }
            elseif(isset($photox['src']['landscape']))
            {
                $full_result_list[] = $photox['src']['landscape'];
            }
            elseif(isset($photox['src']['original']))
            {
                $full_result_list[] = $photox['src']['original'];
            }
            elseif(isset($photox['src']['tiny']))
            {
                $full_result_list[] = $photox['src']['tiny'];
            }
        }
        $items['photos'] = array_slice($items['photos'], 0, $max_res, true);
        if(!isset($aiomatic_Main_Settings['random_results_order']) || $aiomatic_Main_Settings['random_results_order'] != 'on')
        {
            shuffle($items['photos']);
        }
        while($featured_image == '' && isset($items['photos'][$x]))
        {
            $item = $items['photos'][$x];
            if(isset($item['src']['large']))
            {
                if(!in_array($item['src']['large'], $added_img_list))
                {
                    $featured_image = $item['src']['large'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['src']['medium']))
            {
                if(!in_array($item['src']['medium'], $added_img_list))
                {
                    $featured_image = $item['src']['medium'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['src']['small']))
            {
                if(!in_array($item['src']['small'], $added_img_list))
                {
                    $featured_image = $item['src']['small'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['src']['portrait']))
            {
                if(!in_array($item['src']['portrait'], $added_img_list))
                {
                    $featured_image = $item['src']['portrait'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['src']['landscape']))
            {
                if(!in_array($item['src']['landscape'], $added_img_list))
                {
                    $featured_image = $item['src']['landscape'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['src']['original']))
            {
                if(!in_array($item['src']['original'], $added_img_list))
                {
                    $featured_image = $item['src']['original'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['src']['tiny']))
            {
                if(!in_array($item['src']['tiny'], $added_img_list))
                {
                    $featured_image = $item['src']['tiny'];
                    $added_img_list[] = $featured_image;
                }
            }
            if($featured_image != '' && isset($item['url']))
            {
                $original_url = $item['url'];
            }
            $x++;
        }
    }
    return $featured_image;
}
function aiomatic_get_flickr_image($aiomatic_Main_Settings, $query, &$original_url, $max, &$added_img_list = array(), &$full_result_list = array())
{
    if(isset($aiomatic_Main_Settings['image_pool']) && $aiomatic_Main_Settings['image_pool'] != '')
    {
        $max_res = intval($aiomatic_Main_Settings['image_pool']);
    }
    else
    {
        $max_res = 4;
    }
    $original_url = 'https://www.flickr.com';
    $featured_image = '';
    if(isset($aiomatic_Main_Settings['image_query_translate_en']) && $aiomatic_Main_Settings['image_query_translate_en'] == 'on')
    {
        $text_trans = aiomatic_translate_stability($query);
        if($text_trans != $query && !empty($text_trans))
        {
            aiomatic_log_to_file('Flickr Query translated from: "' . $query . '" to: "' . $text_trans . '"');
            $query = $text_trans;
        }
    }
    $feed_uri = 'https://api.flickr.com/services/rest/?method=flickr.photos.search&api_key=' . trim($aiomatic_Main_Settings['flickr_api']) . '&media=photos&per_page=' . esc_html($max) . '&format=php_serial&text=' . urlencode($query);
    if(isset($aiomatic_Main_Settings['flickr_license']) && $aiomatic_Main_Settings['flickr_license'] != '-1')
    {
        $feed_uri .= '&license=' . $aiomatic_Main_Settings['flickr_license'];
    }
    if(isset($aiomatic_Main_Settings['flickr_order']) && $aiomatic_Main_Settings['flickr_order'] != '')
    {
        $feed_uri .= '&sort=' . $aiomatic_Main_Settings['flickr_order'];
    }
    $feed_uri .= '&extras=description,license,date_upload,date_taken,owner_name,icon_server,original_format,last_update,geo,tags,machine_tags,o_dims,views,media,path_alias,url_sq,url_t,url_s,url_q,url_m,url_n,url_z,url_c,url_l,url_o';
     
    {
        $ch               = curl_init();
        if ($ch === FALSE) {
            aiomatic_log_to_file('Failed to init curl for flickr!');
            return false;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Referer: https://www.flickr.com/'));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        if (isset($aiomatic_Main_Settings['max_timeout']) && $aiomatic_Main_Settings['max_timeout'] != '')
        {
            $ztime = intval($aiomatic_Main_Settings['max_timeout']);
        }
        else
        {
            $ztime = 300;
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $ztime);
        curl_setopt($ch, CURLOPT_URL, $feed_uri);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $exec = curl_exec($ch);
        curl_close($ch);
        if (stristr($exec, 'photos') === FALSE) {
            aiomatic_log_to_file('Unrecognized Flickr API response URI: ' . $feed_uri);
            return false;
        }
        $items = unserialize ( $exec );
        if(!isset($items['photos']['photo']))
        {
            aiomatic_log_to_file('Failed to find photo node in response URI: ' . $feed_uri);
            return false;
        }
        if(count($items['photos']['photo']) == 0)
        {
            return $featured_image;
        }
        foreach($items['photos']['photo'] as $photox)
        {
            if(isset($photox['url_o']))
            {
                $full_result_list[] = $photox['url_o'];
            }
            elseif(isset($photox['url_l']))
            {
                $full_result_list[] = $photox['url_l'];
            }
            elseif(isset($photox['url_c']))
            {
                $full_result_list[] = $photox['url_c'];
            }
            elseif(isset($photox['url_z']))
            {
                $full_result_list[] = $photox['url_z'];
            }
            elseif(isset($photox['url_n']))
            {
                $full_result_list[] = $photox['url_n'];
            }
            elseif(isset($photox['url_m']))
            {
                $full_result_list[] = $photox['url_m'];
            }
            elseif(isset($photox['url_q']))
            {
                $full_result_list[] = $photox['url_q'];
            }
            elseif(isset($photox['url_s']))
            {
                $full_result_list[] = $photox['url_s'];
            }
            elseif(isset($photox['url_t']))
            {
                $full_result_list[] = $photox['url_t'];
            }
            elseif(isset($photox['url_sq']))
            {
                $full_result_list[] = $photox['url_sq'];
            }
        }
        $x = 0;
        $items['photos']['photo'] = array_slice($items['photos']['photo'], 0, $max_res, true);
        if(!isset($aiomatic_Main_Settings['random_results_order']) || $aiomatic_Main_Settings['random_results_order'] != 'on')
        {
            shuffle($items['photos']['photo']);
        }
        while($featured_image == '' && isset($items['photos']['photo'][$x]))
        {
            $item = $items['photos']['photo'][$x];
            if(isset($item['url_o']))
            {
                if(!in_array($item['url_o'], $added_img_list))
                {
                    $featured_image = $item['url_o'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_l']))
            {
                if(!in_array($item['url_l'], $added_img_list))
                {
                    $featured_image = $item['url_l'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_c']))
            {
                if(!in_array($item['url_c'], $added_img_list))
                {
                    $featured_image = $item['url_c'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_z']))
            {
                if(!in_array($item['url_z'], $added_img_list))
                {
                    $featured_image = $item['url_z'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_n']))
            {
                if(!in_array($item['url_n'], $added_img_list))
                {
                    $featured_image = $item['url_n'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_m']))
            {
                if(!in_array($item['url_m'], $added_img_list))
                {
                    $featured_image = $item['url_m'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_q']))
            {
                if(!in_array($item['url_q'], $added_img_list))
                {
                    $featured_image = $item['url_q'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_s']))
            {
                if(!in_array($item['url_s'], $added_img_list))
                {
                    $featured_image = $item['url_s'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_t']))
            {
                if(!in_array($item['url_t'], $added_img_list))
                {
                    $featured_image = $item['url_t'];
                    $added_img_list[] = $featured_image;
                }
            }
            elseif(isset($item['url_sq']))
            {
                if(!in_array($item['url_sq'], $added_img_list))
                {
                    $featured_image = $item['url_sq'];
                    $added_img_list[] = $featured_image;
                }
            }
            if($featured_image != '')
            {
                $original_url = 'https://www.flickr.com/photos/' . $item['owner'] . '/' . $item['id'];
            }
            $x++;
        }
    }
    return $featured_image;
}
function aiomatic_get_morguefile_image($app_id, $app_secret, $query, &$original_url, &$added_img_list = array(), &$full_result_list = array())
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(isset($aiomatic_Main_Settings['image_pool']) && $aiomatic_Main_Settings['image_pool'] != '')
    {
        $max_res = intval($aiomatic_Main_Settings['image_pool']);
    }
    else
    {
        $max_res = 4;
    }
    $featured_image = '';
    if(!class_exists('aiomatic_morguefile'))
    {
        require_once (dirname(__FILE__) . "/res/morguefile/mf.api.class.php");
    }
    if(isset($aiomatic_Main_Settings['image_query_translate_en']) && $aiomatic_Main_Settings['image_query_translate_en'] == 'on')
    {
        $text_trans = aiomatic_translate_stability($query);
        if($text_trans != $query && !empty($text_trans))
        {
            aiomatic_log_to_file('MorgueFile query translated from: "' . $query . '" to: "' . $text_trans . '"');
            $query = $text_trans;
        }
    }
    $query = explode(' ', $query);
    $query = $query[0];
    {
        $mf = new aiomatic_morguefile(trim($app_id), $app_secret);
        $rez = $mf->call('/images/search/sort/page/' . $query);
        if ($rez !== FALSE) 
        {
            foreach($rez->doc as $myImg)
            {
                $full_result_list[] = $myImg->file_path_large;
            }
            $rez->doc = array_slice($rez->doc, 0, $max_res, true);
            $chosen_one = $rez->doc[array_rand($rez->doc)];
            if(!isset($aiomatic_Main_Settings['random_results_order']) || $aiomatic_Main_Settings['random_results_order'] != 'on')
            {
                shuffle($chosen_one);
            }
            if (isset($chosen_one->file_path_large)) 
            {
                if(!in_array($chosen_one->file_path_large, $added_img_list))
                {
                    $added_img_list[] = $chosen_one->file_path_large;
                    return $chosen_one->file_path_large;
                }
            }
            else
            {
                return false;
            }
        }
        else
        {
            aiomatic_log_to_file('Error while getting api response from morguefile.');
            return false;
        }
    }
    return $featured_image;
}
function aiomatic_get_pixabay_image($app_id, $query, $lang, $image_type, $orientation, $order, $image_category, $max_width, $min_width, $safe_search, $editors_choice, &$original_url, $get_max = 3, &$added_img_list = array(), &$full_result_list = array())
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(isset($aiomatic_Main_Settings['image_pool']) && $aiomatic_Main_Settings['image_pool'] != '')
    {
        $max_res = intval($aiomatic_Main_Settings['image_pool']);
    }
    else
    {
        $max_res = 4;
    }
    $original_url = 'https://pixabay.com';
    $featured_image = '';
    $feed_uri = 'https://pixabay.com/api/?key=' . trim($app_id);
    if(isset($aiomatic_Main_Settings['image_query_translate_en']) && $aiomatic_Main_Settings['image_query_translate_en'] == 'on')
    {
        $text_trans = aiomatic_translate_stability($query);
        if($text_trans != $query && !empty($text_trans))
        {
            aiomatic_log_to_file('Pixabay query translated from: "' . $query . '" to: "' . $text_trans . '"');
            $query = $text_trans;
        }
    }
    if($query != '')
    {
        $feed_uri .= '&q=' . urlencode($query);
    }
    $feed_uri .= '&per_page=' . $get_max;
    if($lang != '' && $lang != 'any')
    {
        $feed_uri .= '&lang=' . $lang;
    }
    if($image_type != '')
    {
        $feed_uri .= '&image_type=' . $image_type;
    }
    if($orientation != '')
    {
        $feed_uri .= '&orientation=' . $orientation;
    }
    if($order != '')
    {
        $feed_uri .= '&order=' . $order;
    }
    if($image_category != '')
    {
        $feed_uri .= '&category=' . $image_category;
    }
    if($max_width != '')
    {
        $feed_uri .= '&max_width=' . $max_width;
    }
    if($min_width != '')
    {
        $feed_uri .= '&min_width=' . $min_width;
    }
    if($safe_search == '1')
    {
        $feed_uri .= '&safesearch=true';
    }
    if($editors_choice == '1')
    {
        $feed_uri .= '&editors_choice=true';
    }
    $exec = aiomatic_get_web_page($feed_uri);
    if ($exec !== FALSE) 
    {
        if (stristr($exec, '"hits"') !== FALSE) 
        {
            $exec = preg_replace('#^[a-zA-Z0-9]*#', '', $exec);
            $exec = trim($exec, '()');
            $json  = json_decode($exec);
            $items = $json->hits;
            if (count($items) != 0) 
            {
                foreach($items as $item)
                {
                    $full_result_list[] = $item->webformatURL;
                }
                $items = array_slice($items, 0, $max_res, true);
                if(!isset($aiomatic_Main_Settings['random_results_order']) || $aiomatic_Main_Settings['random_results_order'] != 'on')
                {
                    shuffle($items);
                }
                foreach($items as $item)
                {
                    if($featured_image == '' && isset($item->pageURL) && !in_array($item->pageURL, $added_img_list))
                    {
                        $added_img_list[] = $item->pageURL;
                        $featured_image = $item->webformatURL;
                        $original_url = $item->pageURL;
                        break;
                    }
                }
            }
        }
        else
        {
            aiomatic_log_to_file('Unknow response from api: ' . $feed_uri . ' - resp: ' . $exec);
            return false;
        }
    }
    else
    {
        aiomatic_log_to_file('Error while getting api url: ' . $feed_uri);
        return false;
    }
    return $featured_image;
}

function aiomatic_addPostMeta($post_id, $post, $param, $type, $featured_img, $post_topic, $rule_unique_id, $post_link)
{
    if(empty($rule_unique_id))
    {
        $rule_unique_id = $param;
    }
    if(!empty($post_link))
    {
        add_post_meta($post_id, 'aiomatic_rss_link', $post_link);
    }
    add_post_meta($post_id, 'aiomatic_parent_rule', $type . '-' . $rule_unique_id);
    add_post_meta($post_id, 'aiomatic_parent_number', $param);
    add_post_meta($post_id, 'aiomatic_parent_type', $type);
    add_post_meta($post_id, 'aiomatic_enable_pingbacks', $post['aiomatic_enable_pingbacks']);
    add_post_meta($post_id, 'aiomatic_comment_status', $post['comment_status']);
    add_post_meta($post_id, 'aiomatic_extra_categories', $post['extra_categories']);
    add_post_meta($post_id, 'aiomatic_extra_tags', $post['extra_tags']);
    add_post_meta($post_id, 'aiomatic_featured_img', $featured_img);
    add_post_meta($post_id, 'aiomatic_timestamp', $post['aiomatic_timestamp']);
    add_post_meta($post_id, 'aiomatic_source_title', $post['aiomatic_source_title']);
    if($post_topic != '')
    {
        add_post_meta($post_id, 'aiomatic_post_topic', $post_topic);
    }
}
function aiomatic_addPostMeta_special($post_id, $param, $type, $post_topic, $rule_unique_id)
{
    if(empty($rule_unique_id))
    {
        $rule_unique_id = $param;
    }
    add_post_meta($post_id, 'aiomatic_parent_rule', $type . '-' . $rule_unique_id);
    add_post_meta($post_id, 'aiomatic_parent_number', $param);
    add_post_meta($post_id, 'aiomatic_parent_type', $type);
    if($post_topic != '')
    {
        add_post_meta($post_id, 'aiomatic_post_keyword', $post_topic);
    }
}
function aiomatic_endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}
function aiomatic_generate_featured_image($image_url, $post_id)
{
    if(empty($image_url))
    {
        return false;
    }
    $upload_dir = wp_upload_dir();
    if(!function_exists('is_plugin_active'))
    {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['url_image']) && $aiomatic_Main_Settings['url_image'] == 'on' && (is_plugin_active('featured-image-from-url/featured-image-from-url.php') || is_plugin_active('fifu-premium/fifu-premium.php')))
    {
        if(!aiomatic_url_is_image($image_url))
        {
            aiomatic_log_to_file('Provided remote image is not valid: ' . print_r($image_url, true));
            return false;
        }
        if(function_exists('fifu_dev_set_image'))
        {
            fifu_dev_set_image($post_id, $image_url);
        }
        else
        {
            $value = aiomatic_get_formatted_value($image_url, '', $post_id);
            $attach_id = aiomatic_insert_attachment_by($value);
            update_post_meta($post_id, '_thumbnail_id', $attach_id);
            update_post_meta($post_id, 'fifu_image_url', $image_url);
            update_post_meta($attach_id, '_wp_attached_file', ';' . $image_url);
            $attach = get_post( $attach_id );
            if($attach !== null)
            {
                $attach->post_author = 77777;
                wp_update_post( $attach );
            }
        }
        return true;
    }
    global $wp_filesystem;
    if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
        include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
        wp_filesystem($creds);
    }
    $image_data = $wp_filesystem->get_contents($image_url);
    if ($image_data === FALSE || empty($image_data)) {
        $image_data = aiomatic_get_web_page($image_url);
        if ($image_data === FALSE || empty($image_data) || strpos($image_data, '<Message>Access Denied</Message>') !== FALSE || strpos($image_data, '<title>Just a moment...</title>') !== FALSE || strpos($image_data, '<html') !== FALSE) {
            return false;
        }
    }
    $image_data_temp = aiomatic_string_to_string_compress($image_data);
    if($image_data_temp !== false)
    {
        $image_data = $image_data_temp;
    }
    $filename = basename($image_url);
    $temp     = explode("?", $filename);
    $filename = $temp[0];
    $filename = str_replace('%', '-', $filename);
    $filename = str_replace('#', '-', $filename);
    $filename = str_replace('&', '-', $filename);
    $filename = str_replace('{', '-', $filename);
    $filename = str_replace('}', '-', $filename);
    $filename = str_replace('\\', '-', $filename);
    $filename = str_replace('<', '-', $filename);
    $filename = str_replace('>', '-', $filename);
    $filename = str_replace('*', '-', $filename);
    $filename = str_replace('/', '-', $filename);
    $filename = str_replace('$', '-', $filename);
    $filename = str_replace('\'', '-', $filename);
    $filename = str_replace('"', '-', $filename);
    $filename = str_replace(':', '-', $filename);
    $filename = str_replace('@', '-', $filename);
    $filename = str_replace('+', '-', $filename);
    $filename = str_replace('|', '-', $filename);
    $filename = str_replace('=', '-', $filename);
    $filename = str_replace('`', '-', $filename);
    $filename = stripslashes(preg_replace_callback('#(%[a-zA-Z0-9_]*)#', function($matches){ return rand(0, 9); }, preg_quote($filename)));
    $file_parts = pathinfo($filename);
    $post_title = get_the_title($post_id);
    if($post_title != '')
    {
        $post_title = remove_accents( $post_title );
        $invalid = array(
            ' '   => '-',
            '%20' => '-',
            '_'   => '-',
        );
        $post_title = str_replace( array_keys( $invalid ), array_values( $invalid ), $post_title );
        $post_title = preg_replace('/[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0077}\x{E006C}\x{E0073}\x{E007F})|[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0073}\x{E0063}\x{E0074}\x{E007F})|[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0065}\x{E006E}\x{E0067}\x{E007F})|[\x{1F3F4}](?:\x{200D}\x{2620}\x{FE0F})|[\x{1F3F3}](?:\x{FE0F}\x{200D}\x{1F308})|[\x{0023}\x{002A}\x{0030}\x{0031}\x{0032}\x{0033}\x{0034}\x{0035}\x{0036}\x{0037}\x{0038}\x{0039}](?:\x{FE0F}\x{20E3})|[\x{1F415}](?:\x{200D}\x{1F9BA})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F466})|[\x{1F469}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F469})|[\x{1F469}\x{1F468}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F468})|[\x{1F469}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F48B}\x{200D}\x{1F469})|[\x{1F469}\x{1F468}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F48B}\x{200D}\x{1F468})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9BD})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9BC})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9AF})|[\x{1F575}\x{1F3CC}\x{26F9}\x{1F3CB}](?:\x{FE0F}\x{200D}\x{2640}\x{FE0F})|[\x{1F575}\x{1F3CC}\x{26F9}\x{1F3CB}](?:\x{FE0F}\x{200D}\x{2642}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2695}\x{FE0F})|[\x{1F471}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F9CF}\x{1F647}\x{1F926}\x{1F937}\x{1F46E}\x{1F482}\x{1F477}\x{1F473}\x{1F9B8}\x{1F9B9}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F9DE}\x{1F9DF}\x{1F486}\x{1F487}\x{1F6B6}\x{1F9CD}\x{1F9CE}\x{1F3C3}\x{1F46F}\x{1F9D6}\x{1F9D7}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93C}\x{1F93D}\x{1F93E}\x{1F939}\x{1F9D8}](?:\x{200D}\x{2640}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B0})|[\x{1F471}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F9CF}\x{1F647}\x{1F926}\x{1F937}\x{1F46E}\x{1F482}\x{1F477}\x{1F473}\x{1F9B8}\x{1F9B9}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F9DE}\x{1F9DF}\x{1F486}\x{1F487}\x{1F6B6}\x{1F9CD}\x{1F9CE}\x{1F3C3}\x{1F46F}\x{1F9D6}\x{1F9D7}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93C}\x{1F93D}\x{1F93E}\x{1F939}\x{1F9D8}](?:\x{200D}\x{2642}\x{FE0F})|[\x{1F441}](?:\x{FE0F}\x{200D}\x{1F5E8}\x{FE0F})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1F9}\x{1F1FA}](?:\x{1F1FF})|[\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1FA}](?:\x{1F1FE})|[\x{1F1E6}\x{1F1E8}\x{1F1F2}\x{1F1F8}](?:\x{1F1FD})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F7}\x{1F1F9}\x{1F1FF}](?:\x{1F1FC})|[\x{1F1E7}\x{1F1E8}\x{1F1F1}\x{1F1F2}\x{1F1F8}\x{1F1F9}](?:\x{1F1FB})|[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1FB}](?:\x{1F1FA})|[\x{1F1E6}\x{1F1E7}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FE}](?:\x{1F1F9})|[\x{1F1E6}\x{1F1E7}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F7}\x{1F1F8}\x{1F1FA}\x{1F1FC}](?:\x{1F1F8})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1F7})|[\x{1F1E6}\x{1F1E7}\x{1F1EC}\x{1F1EE}\x{1F1F2}](?:\x{1F1F6})|[\x{1F1E8}\x{1F1EC}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F3}](?:\x{1F1F5})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1EE}\x{1F1EF}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1F8}\x{1F1F9}](?:\x{1F1F4})|[\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}](?:\x{1F1F3})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FF}](?:\x{1F1F2})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1EE}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1F1})|[\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1ED}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FD}](?:\x{1F1F0})|[\x{1F1E7}\x{1F1E9}\x{1F1EB}\x{1F1F8}\x{1F1F9}](?:\x{1F1EF})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EB}\x{1F1EC}\x{1F1F0}\x{1F1F1}\x{1F1F3}\x{1F1F8}\x{1F1FB}](?:\x{1F1EE})|[\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1ED})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EA}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}](?:\x{1F1EC})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F9}\x{1F1FC}](?:\x{1F1EB})|[\x{1F1E6}\x{1F1E7}\x{1F1E9}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F7}\x{1F1F8}\x{1F1FB}\x{1F1FE}](?:\x{1F1EA})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1EE}\x{1F1F2}\x{1F1F8}\x{1F1F9}](?:\x{1F1E9})|[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1F9}\x{1F1FB}](?:\x{1F1E8})|[\x{1F1E7}\x{1F1EC}\x{1F1F1}\x{1F1F8}](?:\x{1F1E7})|[\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F6}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}\x{1F1FF}](?:\x{1F1E6})|[\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}-\x{2615}\x{2618}\x{261D}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}-\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}-\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}-\x{267F}\x{2692}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CE}-\x{26CF}\x{26D1}\x{26D3}-\x{26D4}\x{26E9}-\x{26EA}\x{26F0}-\x{26F5}\x{26F7}-\x{26FA}\x{26FD}\x{2702}\x{2705}\x{2708}-\x{270D}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2763}-\x{2764}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F0CF}\x{1F170}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}-\x{1F202}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F23A}\x{1F250}-\x{1F251}\x{1F300}-\x{1F321}\x{1F324}-\x{1F393}\x{1F396}-\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}-\x{1F3F0}\x{1F3F3}-\x{1F3F5}\x{1F3F7}-\x{1F3FA}\x{1F400}-\x{1F4FD}\x{1F4FF}-\x{1F53D}\x{1F549}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F56F}-\x{1F570}\x{1F573}-\x{1F57A}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F590}\x{1F595}-\x{1F596}\x{1F5A4}-\x{1F5A5}\x{1F5A8}\x{1F5B1}-\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}-\x{1F64F}\x{1F680}-\x{1F6C5}\x{1F6CB}-\x{1F6D2}\x{1F6D5}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6EB}-\x{1F6EC}\x{1F6F0}\x{1F6F3}-\x{1F6FA}\x{1F7E0}-\x{1F7EB}\x{1F90D}-\x{1F93A}\x{1F93C}-\x{1F945}\x{1F947}-\x{1F971}\x{1F973}-\x{1F976}\x{1F97A}-\x{1F9A2}\x{1F9A5}-\x{1F9AA}\x{1F9AE}-\x{1F9CA}\x{1F9CD}-\x{1F9FF}\x{1FA70}-\x{1FA73}\x{1FA78}-\x{1FA7A}\x{1FA80}-\x{1FA82}\x{1FA90}-\x{1FA95}]/u', '', $post_title);
        $post_title = preg_replace('/\.(?=.*\.)/', '', $post_title);
        $post_title = preg_replace('/-+/', '-', $post_title);
        $post_title = str_replace('-.', '.', $post_title);
        $post_title = strtolower( $post_title );
        if($post_title == '')
        {
            $post_title = uniqid();
        }
        if(isset($file_parts['extension']))
        {
            switch($file_parts['extension'])
            {
                case "":
                $filename = sanitize_title($post_title) . '.jpg';
                break;
                case NULL:
                $filename = sanitize_title($post_title) . '.jpg';
                break;
                default:
                $filename = sanitize_title($post_title) . '.' . $file_parts['extension'];
                break;
            }
        }
        else
        {
            $filename = sanitize_title($post_title) . '.jpg';
        }
    }
    else
    {
        if(isset($file_parts['extension']))
        {
            switch($file_parts['extension'])
            {
                case "":
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
                case NULL:
                if(!aiomatic_endsWith($filename, '.jpg'))
                    $filename .= '.jpg';
                break;
                default:
                if(!aiomatic_endsWith($filename, '.' . $file_parts['extension']))
                    $filename .= '.' . $file_parts['extension'];
                break;
            }
        }
        else
        {
            if(!aiomatic_endsWith($filename, '.jpg'))
                $filename .= '.jpg';
        }
    }
    $filename = sanitize_file_name($filename);
    if (wp_mkdir_p($upload_dir['path']))
        $file = $upload_dir['path'] . '/' . $post_id . '-' . $filename;
    else
        $file = $upload_dir['basedir'] . '/' . $post_id . '-' . $filename;
    if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
        include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
        wp_filesystem($creds);
    }
    $ret = $wp_filesystem->put_contents($file, $image_data);
    if ($ret === FALSE) {
        return false;
    }
    $wp_filetype = wp_check_filetype($filename, null);
    if($wp_filetype['type'] == '')
    {
        $wp_filetype['type'] = 'image/png';
    }
    $attachment  = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    if ((isset($aiomatic_Main_Settings['resize_height']) && $aiomatic_Main_Settings['resize_height'] !== '') || (isset($aiomatic_Main_Settings['resize_width']) && $aiomatic_Main_Settings['resize_width'] !== ''))
    {
        try
        {
            if(!class_exists('\Eventviva\ImageResize')){require_once (dirname(__FILE__) . "/res/ImageResize/ImageResize.php");}
            $imageRes = new ImageResize($file);
            if (isset($aiomatic_Main_Settings['resize_quality']) && $aiomatic_Main_Settings['resize_quality'] !== '')
            {
                $imageRes->quality_jpg = intval($aiomatic_Main_Settings['resize_quality']);
            }
            else
            {
                $imageRes->quality_jpg = 100;
            }
            if ((isset($aiomatic_Main_Settings['resize_height']) && $aiomatic_Main_Settings['resize_height'] !== '') && (isset($aiomatic_Main_Settings['resize_width']) && $aiomatic_Main_Settings['resize_width'] !== ''))
            {
                $imageRes->resizeToBestFit($aiomatic_Main_Settings['resize_width'], $aiomatic_Main_Settings['resize_height'], true);
            }
            elseif (isset($aiomatic_Main_Settings['resize_width']) && $aiomatic_Main_Settings['resize_width'] !== '')
            {
                $imageRes->resizeToWidth($aiomatic_Main_Settings['resize_width'], true);
            }
            elseif (isset($aiomatic_Main_Settings['resize_height']) && $aiomatic_Main_Settings['resize_height'] !== '')
            {
                $imageRes->resizeToHeight($aiomatic_Main_Settings['resize_height'], true);
            }
            $imageRes->save($file);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to resize featured image: ' . $image_url . ' to sizes ' . $aiomatic_Main_Settings['resize_width'] . ' - ' . $aiomatic_Main_Settings['resize_height'] . '. Exception thrown ' . esc_html($e->getMessage()) . '!');
        }
    }
    $attach_id   = wp_insert_attachment($attachment, $file, $post_id);
    if ($attach_id === 0) {
        return false;
    }
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);
    $res2 = set_post_thumbnail($post_id, $attach_id);
    if ($res2 === FALSE) {
        return false;
    }
    $post_title = get_the_title($post_id);
    if($post_title != '')
    {
        update_post_meta($attach_id, '_wp_attachment_image_alt', $post_title);
    }
    return true;
}

function aiomatic_assign_featured_image_path($filename, $post_id)
{
    $wp_filetype = wp_check_filetype($filename, null);
    if($wp_filetype['type'] == '')
    {
        $wp_filetype['type'] = 'image/png';
    }
    $post_title = get_the_title($post_id);
    $attachment  = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => $post_title,
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if ((isset($aiomatic_Main_Settings['resize_height']) && $aiomatic_Main_Settings['resize_height'] !== '') || (isset($aiomatic_Main_Settings['resize_width']) && $aiomatic_Main_Settings['resize_width'] !== ''))
    {
        try
        {
            if(!class_exists('\Eventviva\ImageResize')){require_once (dirname(__FILE__) . "/res/ImageResize/ImageResize.php");}
            $imageRes = new ImageResize($filename);
            if (isset($aiomatic_Main_Settings['resize_quality']) && $aiomatic_Main_Settings['resize_quality'] !== '')
            {
                $imageRes->quality_jpg = intval($aiomatic_Main_Settings['resize_quality']);
            }
            else
            {
                $imageRes->quality_jpg = 100;
            }
            if ((isset($aiomatic_Main_Settings['resize_height']) && $aiomatic_Main_Settings['resize_height'] !== '') && (isset($aiomatic_Main_Settings['resize_width']) && $aiomatic_Main_Settings['resize_width'] !== ''))
            {
                $imageRes->resizeToBestFit($aiomatic_Main_Settings['resize_width'], $aiomatic_Main_Settings['resize_height'], true);
            }
            elseif (isset($aiomatic_Main_Settings['resize_width']) && $aiomatic_Main_Settings['resize_width'] !== '')
            {
                $imageRes->resizeToWidth($aiomatic_Main_Settings['resize_width'], true);
            }
            elseif (isset($aiomatic_Main_Settings['resize_height']) && $aiomatic_Main_Settings['resize_height'] !== '')
            {
                $imageRes->resizeToHeight($aiomatic_Main_Settings['resize_height'], true);
            }
            $imageRes->save($filename);
        }
        catch(Exception $e)
        {
            aiomatic_log_to_file('Failed to resize featured image: ' . $filename . ' to sizes ' . $aiomatic_Main_Settings['resize_width'] . ' - ' . $aiomatic_Main_Settings['resize_height'] . '. Exception thrown ' . esc_html($e->getMessage()) . '!');
        }
    }
    $attach_id   = wp_insert_attachment($attachment, $filename, $post_id);
    if ($attach_id === 0) {
        return false;
    }
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
    wp_update_attachment_metadata($attach_id, $attach_data);
    $res2 = set_post_thumbnail($post_id, $attach_id);
    if ($res2 === FALSE) {
        return false;
    }
    if($post_title != '')
    {
        update_post_meta($attach_id, '_wp_attachment_image_alt', $post_title);
    }
    return true;
}

function aiomatic_hour_diff($date1, $date2)
{
    $date1 = new DateTime($date1);
    $date2 = new DateTime($date2);
    
    $number1 = (int) $date1->format('U');
    $number2 = (int) $date2->format('U');
    return ($number1 - $number2) / 60;
}

function aiomatic_minute_diff($date1, $date2)
{
    $date1 = new DateTime($date1);
    $date2 = new DateTime($date2);
    
    $number1 = (int) $date1->format('U');
    $number2 = (int) $date2->format('U');
    return ($number1 - $number2);
}

function aiomatic_add_minute($date, $minute)
{
    $date1 = new DateTime($date);
    $date1->modify("$minute minutes");
    $date1 = (array)$date1;
    foreach ($date1 as $key => $value) {
        if ($key == 'date') {
            return $value;
        }
    }
    return $date;
}
function aiomatic_add_hour($date, $hour)
{
    $date1 = new DateTime($date);
    $date1->modify("$hour hours");
    $date1 = (array)$date1;
    foreach ($date1 as $key => $value) {
        if ($key == 'date') {
            return $value;
        }
    }
    return $date;
}

function aiomatic_wp_custom_css_files($src, $cont)
{
    $name = md5(get_bloginfo());
    wp_enqueue_style($name . '-thumbnail-css-' . $cont, $src, __FILE__);
}

function aiomatic_get_date_now($param = 'now')
{
    $date = new DateTime($param);
    $date = (array)$date;
    foreach ($date as $key => $value) {
        if ($key == 'date') {
            return $value;
        }
    }
    return '';
}

function aiomatic_create_terms($taxonomy, $parent, $terms_str)
{
    if(is_array($terms_str))
    {
        $terms = $terms_str;
    }
    else
    {
        $terms          = explode('/', $terms_str);
    }
    $categories     = array();
    $parent_term_id = $parent;
    foreach ($terms as $term) {
        $res = term_exists($term, $taxonomy, $parent);
        if ($res != NULL && $res != 0 && count($res) > 0 && isset($res['term_id'])) {
            $parent_term_id = $res['term_id'];
            $categories[]   = $parent_term_id;
        } 
        else 
        {
            if($parent === null)
            {
                $insert_parent = 0;
            }
            else
            {
                $insert_parent = $parent;
            }
            $new_term = wp_insert_term($term, $taxonomy, array(
                'parent' => $insert_parent
            ));
            if (!is_wp_error( $new_term ) && $new_term != NULL && $new_term != 0 && count($new_term) > 0 && isset($new_term['term_id'])) {
                $parent_term_id = $new_term['term_id'];
                $categories[]   = $parent_term_id;
            }
        }
    }
    return $categories;
}
function aiomatic_getExcerpt($the_content)
{
    $preview = aiomatic_strip_html_tags($the_content);
    $preview = wp_trim_words($preview, 55);
    return $preview;
}

function aiomatic_getPlainContent($the_content)
{
    $preview = aiomatic_strip_html_tags($the_content);
    $preview = wp_trim_words($preview, 999999);
    return $preview;
}
function aiomatic_getItemImage($img)
{
    if(empty($img))
    {
        return '';
    }
    $preview = '<img src="' . esc_url_raw($img) . '" alt="image" />';
    return $preview;
}
function aiomatic_get_session_id() {
    $name = md5(get_bloginfo());
    if ( isset(  $_COOKIE[$name . '_session_id'] ) ) {
        return $_COOKIE[$name . '_session_id'];
    }
    else
    {
        $unique = uniqid();
        error_reporting(0);
        setcookie($name . "_session_id", $unique, 0);
        error_reporting(E_ALL);
        return $unique;
    }
    return "N/A";
}
add_action( 'enqueue_block_editor_assets', 'aiomatic_enqueue_block_editor_assets' );
function aiomatic_enqueue_block_editor_assets() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $name = md5(get_bloginfo());
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on')
    {
        if (!isset($aiomatic_Main_Settings['no_post_editor']) || $aiomatic_Main_Settings['no_post_editor'] !== 'on')
        {
            $all_models = aiomatic_get_all_models(true);
            $all_edit_models = array_merge($all_models, AIOMATIC_EDIT_MODELS);
            wp_register_style($name . '-browser-style', plugins_url('styles/aiomatic-browser.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
            wp_enqueue_style($name . '-browser-style');
            $block_js_display = 'scripts/display-posts.js';
            wp_enqueue_script(
                $name . '-display-block-js', 
                plugins_url( $block_js_display, __FILE__ ), 
                array(
                    'wp-blocks',
                    'wp-i18n',
                    'wp-element',
                ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_lisx  = 'scripts/list-posts.js';
            wp_enqueue_script(
                $name . '-list-block-js', 
                plugins_url( $block_js_lisx, __FILE__ ), 
                array(
                    'wp-blocks',
                    'wp-i18n',
                    'wp-element',
                ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_article   = 'scripts/aiomatic-article.js';
            wp_enqueue_script(
                $name . '-article', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            wp_localize_script($name . '-article', 'aiomatic_object', array(
                'models' => $all_models
            ));
            $block_js_image   = 'scripts/aiomatic-image.js';
            wp_enqueue_script(
                $name . '-image', 
                plugins_url( $block_js_image, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_image   = 'scripts/aiomatic-stable-image.js';
            wp_enqueue_script(
                $name . '-stable-image', 
                plugins_url( $block_js_image, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_image   = 'scripts/aiomatic-midjourney-image.js';
            wp_enqueue_script(
                $name . '-midjourney-image', 
                plugins_url( $block_js_image, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_image   = 'scripts/aiomatic-replicate-image.js';
            wp_enqueue_script(
                $name . '-replicate-image', 
                plugins_url( $block_js_image, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_list   = 'scripts/sidebar.js';
            wp_enqueue_script(
                $name . '-sidebar-js', 
                plugins_url( $block_js_list, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $metavalue = '';
            $pid = get_the_ID();
            $custom_name = 'aiomatic_published';
            if($pid !== false) {
                $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
                if (isset($aiomatic_Spinner_Settings['custom_name']) && trim($aiomatic_Spinner_Settings['custom_name']) != '') {
                    $custom_name = trim($aiomatic_Spinner_Settings['custom_name']);
                    $custom_name = str_replace('%%current_date%%', date("Y-m-d"), $custom_name);
                } else {
                    $custom_name = 'aiomatic_published';
                }
                $metavalue = get_post_meta($pid, $custom_name, true);
            }
            $temp_list = array();
            $args = array(
                'post_type'      => 'aiomatic_editor_temp',
                'posts_per_page' => -1,
                'fields'         => 'ids'
            );
            $post_ids = get_posts($args);
            $temp_list = array();
            if (!empty($post_ids)) {
                foreach ($post_ids as $post_id) {
                    $temp_list[$post_id] = get_the_title($post_id);
                }
            }
            wp_reset_postdata();
            wp_localize_script($name . '-sidebar-js', 'aiomatic_gut', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('openai-ajax-nonce'),
                'metavalue' => $metavalue,
                'templates' => $temp_list,
                'metaKey' => $custom_name
            ));
            $block_js_article   = 'scripts/aiomatic-completion.js';
            wp_enqueue_script(
                $name . '-completion', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            wp_localize_script($name . '-completion', 'aiomatic_object', array(
                'models' => $all_models
            ));
            $block_js_article   = 'scripts/aiomatic-editing.js';
            wp_enqueue_script(
                $name . '-editing', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            wp_localize_script($name . '-editing', 'aiomatic_object', array(
                'models' => $all_edit_models
            ));
            $block_js_article   = 'scripts/aiomatic-image-generator.js';
            wp_enqueue_script(
                $name . '-image-generator', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_article   = 'scripts/aiomatic-stable-image-generator.js';
            wp_enqueue_script(
                $name . '-stable-image-generator', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_article   = 'scripts/aiomatic-midjourney-image-generator.js';
            wp_enqueue_script(
                $name . '-midjourney-image-generator', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_article   = 'scripts/aiomatic-replicate-image-generator.js';
            wp_enqueue_script(
                $name . '-replicate-image-generator', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_article   = 'scripts/aiomatic-chat-selector.js';
            wp_enqueue_script(
                $name . '-chat-selector', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            $block_js_article   = 'scripts/aiomatic-chat.js';
            wp_enqueue_script(
                $name . '-chat', 
                plugins_url( $block_js_article, __FILE__ ), 
                array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data' ),
                AIOMATIC_MAJOR_VERSION
            );
            wp_localize_script($name . '-chat', 'aiomatic_object', array(
                'models' => $all_models
            ));
            if (!isset($aiomatic_Main_Settings['assistant_disable']) || ($aiomatic_Main_Settings['assistant_disable'] == 'back' || $aiomatic_Main_Settings['assistant_disable'] == 'both'))
            {
                wp_enqueue_script(
                    $name . '-gutenberg',
                    plugins_url('/scripts/gutenberg-editor.js', __FILE__),
                    array('wp-rich-text'),
                    AIOMATIC_MAJOR_VERSION,
                    true
                );
                $assistant_placement = 'below';
                if (isset($aiomatic_Main_Settings['assistant_placement']) && $aiomatic_Main_Settings['assistant_placement'] != '') 
                {
                    $assistant_placement = $aiomatic_Main_Settings['assistant_placement'];
                }
                $prompts  = aiomatic_get_assistant();
                if(!is_array($prompts))
                {
                    $prompts = array();
                }
                $nonce = wp_create_nonce('wp_rest');
                wp_localize_script($name . '-gutenberg', 'aiomatic', array(
                    'nonce'  =>  $nonce,
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'prompts' => $prompts,
                    'placement' => $assistant_placement,
                    'xicon' => plugins_url('/images/icon.png', __FILE__)
                ));
                $reg_css_code = '.aiomatic_editor_icon button{background-image: url("' . plugins_url('/images/icon.png', __FILE__) . '");background-size: 32px;background-repeat: no-repeat;background-position: center;}';
                wp_register_style( $name . '-plugin-reg-style', false, false, AIOMATIC_MAJOR_VERSION );
                wp_enqueue_style( $name . '-plugin-reg-style' );
                wp_add_inline_style( $name . '-plugin-reg-style', $reg_css_code );
            }
        }
    }
}
function aiomatic_save_forms($formid, $title, $prompt, $model, $header, $submit, $description, $response, $max, $temperature, $topp, $presence, $frequency, $type, $aiomaticfields, $assistant_id, $streaming_enabled, $editor, $advanced)
{
    $aiomatic_result = array('status' => 'error', 'msg' => 'Something went wrong forms saving');
    $forms_data = array(
        'post_type' => 'aiomatic_forms',
        'post_title' => $title,
        'post_content' => $description,
        'post_status' => 'publish'
    );
    if(!empty($formid))
    {
        $forms_data['ID'] = $formid;
    }
    if (!empty($post_type)) {
        $forms_data['post_type'] = $post_type;
    }
    remove_filter('content_save_pre', 'wp_filter_post_kses');
    remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');remove_filter('title_save_pre', 'wp_filter_kses');
    if(!empty($formid))
    {
        $forms_id = wp_update_post($forms_data);
    }
    else
    {
        $forms_id = wp_insert_post($forms_data);
    }
    add_filter('content_save_pre', 'wp_filter_post_kses');
    add_filter('content_filtered_save_pre', 'wp_filter_post_kses');add_filter('title_save_pre', 'wp_filter_kses');
    if(is_wp_error($forms_id))
    {
        $aiomatic_result['msg'] = $forms_id->get_error_message();
    }
    elseif($forms_id === 0)
    {
        $aiomatic_result['msg'] = 'Failed to insert form to database: ' . $title;
    }
    else 
    {
        update_post_meta($forms_id, 'prompt', $prompt);
        update_post_meta($forms_id, 'model', $model);
        update_post_meta($forms_id, 'assistant_id', $assistant_id);
        update_post_meta($forms_id, 'header', $header);
        update_post_meta($forms_id, 'editor', $editor);
        update_post_meta($forms_id, 'advanced', $advanced);
        update_post_meta($forms_id, 'submit', $submit);
        update_post_meta($forms_id, 'max', $max);
        update_post_meta($forms_id, 'temperature', $temperature);
        update_post_meta($forms_id, 'topp', $topp);
        update_post_meta($forms_id, 'presence', $presence);
        update_post_meta($forms_id, 'frequency', $frequency);
        update_post_meta($forms_id, 'streaming_enabled', $streaming_enabled);
        update_post_meta($forms_id, 'response', $response);
        update_post_meta($forms_id, 'type', $type);
        update_post_meta($forms_id, '_aiomaticfields', $aiomaticfields);
        $aiomatic_result['status'] = 'success';
        $aiomatic_result['id'] = $forms_id;
    }
    return $aiomatic_result;
}
function aiomatic_save_persona($title, $prompt, $description, $first_message, $avatar)
{
    $aiomatic_result = array('status' => 'error', 'msg' => 'Something went wrong persona saving');
    $persona_data = array(
        'post_type' => 'aiomatic_personas',
        'post_title' => $title,
        'post_content' => $prompt,
        'post_excerpt' => $description,
        'post_status' => 'publish'
    );
    remove_filter('content_save_pre', 'wp_filter_post_kses');
    remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');remove_filter('title_save_pre', 'wp_filter_kses');
    $persona_id = wp_insert_post($persona_data);
    add_filter('content_save_pre', 'wp_filter_post_kses');
    add_filter('content_filtered_save_pre', 'wp_filter_post_kses');add_filter('title_save_pre', 'wp_filter_kses');
    if(is_wp_error($persona_id))
    {
        $aiomatic_result['msg'] = $persona_id->get_error_message();
    }
    elseif($persona_id === 0)
    {
        $aiomatic_result['msg'] = 'Failed to insert persona to database: ' . $title;
    }
    else 
    {
        if(!empty($first_message))
        {
            update_post_meta($persona_id, '_persona_first_message', sanitize_text_field($first_message));
        }
        if(!empty($avatar))
        {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $res2 = set_post_thumbnail($persona_id, $avatar);
            if ($res2 === FALSE) 
            {
                $aiomatic_result['msg'] = 'Failed to insert persona avatar to database: ' . $avatar;
            }
        }
        $aiomatic_result['status'] = 'success';
        $aiomatic_result['id'] = $persona_id;
    }
    return $aiomatic_result;
}
require(dirname(__FILE__) . "/res/StatisticsClass.php");
require(dirname(__FILE__) . "/res/QueryClass.php");
$aiomatic_stats = new Aiomatic_Statistics();
add_action('init', 'aiomatic_create_taxonomy', 0);
function aiomatic_create_taxonomy()
{
    if(AIOMATIC_IS_DEBUG === true)
    {
        $labels = array(
            'name' => 'AI Training File',
            'all_items' => 'All AI Training Files',
            'singular_name' => 'aiomatic_file',
            'add_new' => 'New AI Training File' ,
            'add_new_item' => 'Add New AI Training File',
            'edit_item' => 'Edit AI Training File',
            'new_item' => 'New AI Training File',
            'view_item' => 'View AI Training File',
            'search_items' => 'Search AI Training Files',
            'not_found' => 'No AI Training Files found',
            'not_found_in_trash' => 'No AI Training File found in Trash',
            'parent_item_colon' => 'Parent AI Training Files:',
            'menu_name' => 'AI Training Files',
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => false,
            'description' => 'AI Training Files',
            'supports' => array( 'title', 'editor', 'custom-fields' ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => false,
            'menu_position' => PHP_INT_MAX,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'has_archive' => false,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            'capability_type' => 'post'
        );
        $admin_caps = array('capabilities' => array(
            'edit_post'          => 'access_aiomatic_menu',
            'read_post'          => 'access_aiomatic_menu',
            'delete_post'        => 'access_aiomatic_menu',
            'edit_posts'         => 'access_aiomatic_menu',
            'edit_others_posts'  => 'access_aiomatic_menu',
            'delete_posts'       => 'access_aiomatic_menu',
            'publish_posts'      => 'access_aiomatic_menu',
            'read_private_posts' => 'access_aiomatic_menu'
        ));
        $args = array_merge($args, $admin_caps);
        register_post_type( 'aiomatic_file', $args);

        $labels = array(
            'name' => 'AI Conversion File',
            'all_items' => 'All AI Conversion Files',
            'singular_name' => 'aiomatic_convert',
            'add_new' => 'New AI Conversion File' ,
            'add_new_item' => 'Add New AI Conversion File',
            'edit_item' => 'Edit AI Conversion File',
            'new_item' => 'New AI Conversion File',
            'view_item' => 'View AI Conversion File',
            'search_items' => 'Search AI Conversion Files',
            'not_found' => 'No AI Conversion Files found',
            'not_found_in_trash' => 'No AI Conversion File found in Trash',
            'parent_item_colon' => 'Parent AI Conversion Files:',
            'menu_name' => 'AI Conversion Files',
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => false,
            'description' => 'AI Conversion Files',
            'supports' => array( 'title', 'editor', 'custom-fields' ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => false,
            'menu_position' => PHP_INT_MAX,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'has_archive' => false,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            'capability_type' => 'post'
        );
        $admin_caps = array('capabilities' => array(
            'edit_post'          => 'access_aiomatic_menu',
            'read_post'          => 'access_aiomatic_menu',
            'delete_post'        => 'access_aiomatic_menu',
            'edit_posts'         => 'access_aiomatic_menu',
            'edit_others_posts'  => 'access_aiomatic_menu',
            'delete_posts'       => 'access_aiomatic_menu',
            'publish_posts'      => 'access_aiomatic_menu',
            'read_private_posts' => 'access_aiomatic_menu'
        ));
        $args = array_merge($args, $admin_caps);
        register_post_type( 'aiomatic_convert', $args);

        $labels = array(
            'name' => 'AI Finetune',
            'all_items' => 'All AI Finetunes',
            'singular_name' => 'aiomatic_finetune',
            'add_new' => 'New AI Finetune' ,
            'add_new_item' => 'Add New AI Finetune',
            'edit_item' => 'Edit AI Finetune',
            'new_item' => 'New AI Finetune',
            'view_item' => 'View AI Finetune',
            'search_items' => 'Search AI Finetunes',
            'not_found' => 'No AI Finetunes found',
            'not_found_in_trash' => 'No AI Finetune found in Trash',
            'parent_item_colon' => 'Parent AI Finetune:',
            'menu_name' => 'AI Finetune',
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => false,
            'description' => 'AI Finetune',
            'supports' => array( 'title', 'editor', 'custom-fields' ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => false,
            'menu_position' => PHP_INT_MAX,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'has_archive' => false,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            'capability_type' => 'post'
        );
        $admin_caps = array('capabilities' => array(
            'edit_post'          => 'access_aiomatic_menu',
            'read_post'          => 'access_aiomatic_menu',
            'delete_post'        => 'access_aiomatic_menu',
            'edit_posts'         => 'access_aiomatic_menu',
            'edit_others_posts'  => 'access_aiomatic_menu',
            'delete_posts'       => 'access_aiomatic_menu',
            'publish_posts'      => 'access_aiomatic_menu',
            'read_private_posts' => 'access_aiomatic_menu'
        ));
        $args = array_merge($args, $admin_caps);
        register_post_type( 'aiomatic_finetune', $args);
    }
    
    $labels = array(
        'name' => 'AI Embedding',
        'all_items' => 'All AI Embeddings',
        'singular_name' => 'aiomatic_embeddings',
        'add_new' => 'New AI Embedding' ,
        'add_new_item' => 'Add New AI Embeddings',
        'edit_item' => 'Edit AI Embeddings',
        'new_item' => 'New AI Embeddings',
        'view_item' => 'View AI Embeddings',
        'search_items' => 'Search AI Embeddings',
        'not_found' => 'No AI Embeddings found',
        'not_found_in_trash' => 'No AI Embeddings found in Trash',
        'parent_item_colon' => 'Parent AI Embeddings:',
        'menu_name' => 'AI Embeddings',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'AI Embeddings',
        'supports' => array( 'title', 'editor', 'custom-fields' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_embeddings', $args);

    $labels = array(
        'name' => 'AI Remote Chatbot',
        'all_items' => 'All AI Remote Chatbot',
        'singular_name' => 'aiomatic_remote_chat',
        'add_new' => 'New AI Remote Chatbot' ,
        'add_new_item' => 'Add New AI Remote Chatbots',
        'edit_item' => 'Edit AI Remote Chatbots',
        'new_item' => 'New AI Remote Chatbots',
        'view_item' => 'View AI Remote Chatbots',
        'search_items' => 'Search AI Remote Chatbots',
        'not_found' => 'No AI Remote Chatbots found',
        'not_found_in_trash' => 'No AI Remote Chatbots found in Trash',
        'parent_item_colon' => 'Parent AI Remote Chatbots:',
        'menu_name' => 'AI Remote Chatbots',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'AI Remote Chatbots',
        'supports' => array( 'title', 'editor' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => true,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'page',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_remote_chat', $args);

    $labels = array(
        'name' => 'AI Form',
        'all_items' => 'All AI Forms',
        'singular_name' => 'aiomatic_forms',
        'add_new' => 'New AI Form' ,
        'add_new_item' => 'Add New AI Forms',
        'edit_item' => 'Edit AI Forms',
        'new_item' => 'New AI Forms',
        'view_item' => 'View AI Forms',
        'search_items' => 'Search AI Forms',
        'not_found' => 'No AI Forms found',
        'not_found_in_trash' => 'No AI Forms found in Trash',
        'parent_item_colon' => 'Parent AI Forms:',
        'menu_name' => 'AI Forms',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'AI Forms',
        'supports' => array( 'title', 'editor', 'custom-fields' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_forms', $args);

    $labels = array(
        'name' => 'AI Persona',
        'all_items' => 'All AI Personas',
        'singular_name' => 'aiomatic_personas',
        'add_new' => 'New AI Persona' ,
        'add_new_item' => 'Add New AI Persona',
        'edit_item' => 'Edit AI Persona',
        'new_item' => 'New AI Persona',
        'view_item' => 'View AI Persona',
        'search_items' => 'Search AI Persona',
        'not_found' => 'No AI Persona found',
        'featured_image' => 'Persona Avatar',
        'set_featured_image' => 'Set Persona Avatar',
        'remove_featured_image' => 'Remove Persona Avatar',
        'use_featured_image' => 'Use as Persona Avatar',
        'not_found_in_trash' => 'No AI Persona found in Trash',
        'parent_item_colon' => 'Parent AI Persona:',
        'menu_name' => 'AI Personas',
        'item_published' => 'Post published.',
        'item_published_privately' => 'Post published privately.',
        'item_reverted_to_draft' => 'Post reverted to draft.',
        'item_trashed' => 'Post trashed.',
        'item_scheduled' => 'Post scheduled.',
        'item_updated' => 'Post updated.',
        'item_link' => 'Post Link',
        'item_link_description' => 'A link to a post.',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'AI Personas',
        'supports' => array( 'title', 'thumbnail', 'excerpt', 'editor', 'custom-fields' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_personas', $args);

    $labels = array(
        'name' => 'AI Assistant',
        'all_items' => 'All AI Assistants',
        'singular_name' => 'aiomatic_assistants',
        'add_new' => 'New AI Assistant' ,
        'add_new_item' => 'Add New AI Assistant',
        'edit_item' => 'Edit AI Assistant',
        'new_item' => 'New AI Assistant',
        'view_item' => 'View AI Assistant',
        'search_items' => 'Search AI Assistant',
        'not_found' => 'No AI Assistant found',
        'featured_image' => 'Assistant Avatar',
        'set_featured_image' => 'Set Assistant Avatar',
        'remove_featured_image' => 'Remove Assistant Avatar',
        'use_featured_image' => 'Use as Assistant Avatar',
        'not_found_in_trash' => 'No AI Assistant found in Trash',
        'parent_item_colon' => 'Parent AI Assistant:',
        'menu_name' => 'AI Assistants',
        'item_published' => 'Post published.',
        'item_published_privately' => 'Post published privately.',
        'item_reverted_to_draft' => 'Post reverted to draft.',
        'item_trashed' => 'Post trashed.',
        'item_scheduled' => 'Post scheduled.',
        'item_updated' => 'Post updated.',
        'item_link' => 'Post Link',
        'item_link_description' => 'A link to a post.',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'AI Assistants',
        'supports' => array( 'title', 'thumbnail', 'excerpt', 'editor', 'custom-fields' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_assistants', $args);

    $labels = array(
        'name' => 'AI Batch Requests',
        'all_items' => 'All AI Batch Requests',
        'singular_name' => 'aiomatic_batch',
        'add_new' => 'New AI Batch Requests' ,
        'add_new_item' => 'Add New AI Batch Requests',
        'edit_item' => 'Edit AI Batch Requests',
        'new_item' => 'New AI Batch Requests',
        'view_item' => 'View AI Batch Requests',
        'search_items' => 'Search AI Batch Requests',
        'not_found' => 'No AI Batch Requests found',
        'featured_image' => 'Batch Requests Avatar',
        'set_featured_image' => 'Set Batch Requests Avatar',
        'remove_featured_image' => 'Remove Batch Requests Avatar',
        'use_featured_image' => 'Use as Batch Requests Avatar',
        'not_found_in_trash' => 'No AI Batch Requests found in Trash',
        'parent_item_colon' => 'Parent AI Batch Requests:',
        'menu_name' => 'AI Batch Requests',
        'item_published' => 'Post published.',
        'item_published_privately' => 'Post published privately.',
        'item_reverted_to_draft' => 'Post reverted to draft.',
        'item_trashed' => 'Post trashed.',
        'item_scheduled' => 'Post scheduled.',
        'item_updated' => 'Post updated.',
        'item_link' => 'Post Link',
        'item_link_description' => 'A link to a post.',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'AI Batch Requests',
        'supports' => array( 'title', 'thumbnail', 'excerpt', 'editor', 'custom-fields' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_batches', $args);

    $labels = array(
        'name' => 'OmniBlock Templates',
        'all_items' => 'All OmniBlock Templates',
        'singular_name' => 'aiomatic_omni_temp',
        'add_new' => 'New OmniBlock Template' ,
        'add_new_item' => 'Add New OmniBlock Template',
        'edit_item' => 'Edit OmniBlock Template',
        'new_item' => 'New OmniBlock Template',
        'view_item' => 'View OmniBlock Template',
        'search_items' => 'Search OmniBlock Templates',
        'not_found' => 'No OmniBlock Templates found',
        'featured_image' => 'OmniBlock Templates Avatar',
        'set_featured_image' => 'Set OmniBlock Templates Avatar',
        'remove_featured_image' => 'Remove OmniBlock Templates Avatar',
        'use_featured_image' => 'Use as OmniBlock Templates Avatar',
        'not_found_in_trash' => 'No OmniBlock Templates found in Trash',
        'parent_item_colon' => 'Parent OmniBlock Templates:',
        'menu_name' => 'OmniBlock Templates',
        'item_published' => 'Post published.',
        'item_published_privately' => 'Post published privately.',
        'item_reverted_to_draft' => 'Post reverted to draft.',
        'item_trashed' => 'Post trashed.',
        'item_scheduled' => 'Post scheduled.',
        'item_updated' => 'Post updated.',
        'item_link' => 'Post Link',
        'item_link_description' => 'A link to a post.',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'OmniBlock Templates',
        'supports' => array( 'title', 'editor' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_omni_temp', $args);
    register_taxonomy(
        'ai_template_categories',
        'aiomatic_omni_temp',
        array(
            'hierarchical' => true,
            'label' => 'Category',
            'query_var' => true
        )
    );

    $labels = array(
        'name' => 'AI Content Editor Templates',
        'all_items' => 'All AI Content Editor Templates',
        'singular_name' => 'aiomatic_editor_temp',
        'add_new' => 'New AI Content Editor Template' ,
        'add_new_item' => 'Add New AI Content Editor Template',
        'edit_item' => 'Edit AI Content Editor Template',
        'new_item' => 'New AI Content Editor Template',
        'view_item' => 'View AI Content Editor Template',
        'search_items' => 'Search AI Content Editor Templates',
        'not_found' => 'No AI Content Editor Templates found',
        'featured_image' => 'AI Content Editor Templates Avatar',
        'set_featured_image' => 'Set AI Content Editor Templates Avatar',
        'remove_featured_image' => 'Remove AI Content Editor Templates Avatar',
        'use_featured_image' => 'Use as AI Content Editor Templates Avatar',
        'not_found_in_trash' => 'No AI Content Editor Templates found in Trash',
        'parent_item_colon' => 'Parent AI Content Editor Templates:',
        'menu_name' => 'AI Content Editor Templates',
        'item_published' => 'Post published.',
        'item_published_privately' => 'Post published privately.',
        'item_reverted_to_draft' => 'Post reverted to draft.',
        'item_trashed' => 'Post trashed.',
        'item_scheduled' => 'Post scheduled.',
        'item_updated' => 'Post updated.',
        'item_link' => 'Post Link',
        'item_link_description' => 'A link to a post.',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'AI Content Editor Templates',
        'supports' => array( 'title', 'editor' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_editor_temp', $args);

    $labels = array(
        'name' => 'OmniBlock File',
        'all_items' => 'All OmniBlock Files',
        'singular_name' => 'aiomatic_omni_file',
        'add_new' => 'New OmniBlock File' ,
        'add_new_item' => 'Add New OmniBlock File',
        'edit_item' => 'Edit OmniBlock File',
        'new_item' => 'New OmniBlock File',
        'view_item' => 'View OmniBlock File',
        'search_items' => 'Search OmniBlock Files',
        'not_found' => 'No OmniBlock Files found',
        'featured_image' => 'OmniBlock File Avatar',
        'set_featured_image' => 'Set OmniBlock File Avatar',
        'remove_featured_image' => 'Remove OmniBlock File Avatar',
        'use_featured_image' => 'Use as OmniBlock File Avatar',
        'not_found_in_trash' => 'No OmniBlock Files found in Trash',
        'parent_item_colon' => 'Parent OmniBlock File:',
        'menu_name' => 'OmniBlock File',
        'item_published' => 'Post published.',
        'item_published_privately' => 'Post published privately.',
        'item_reverted_to_draft' => 'Post reverted to draft.',
        'item_trashed' => 'Post trashed.',
        'item_scheduled' => 'Post scheduled.',
        'item_updated' => 'Post updated.',
        'item_link' => 'Post Link',
        'item_link_description' => 'A link to a post.',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'OmniBlock File',
        'supports' => array( 'title', 'editor', 'custom-fields' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_omni_file', $args);
    register_taxonomy(
        'ai_file_type',
        'aiomatic_omni_file',
        array(
            'hierarchical' => true,
            'label' => 'Location',
            'query_var' => true
        )
    );

    $labels = array(
        'name' => 'Chatbot Theme',
        'all_items' => 'All Chatbot Themes',
        'singular_name' => 'aiomatic_themes',
        'add_new' => 'New Chatbot Theme' ,
        'add_new_item' => 'Add New Chatbot Theme',
        'edit_item' => 'Edit Chatbot Theme',
        'new_item' => 'New Chatbot Theme',
        'view_item' => 'View Chatbot Theme',
        'search_items' => 'Search Chatbot Theme',
        'not_found' => 'No Chatbot Theme found',
        'featured_image' => 'Theme Image',
        'set_featured_image' => 'Set Theme Image',
        'remove_featured_image' => 'Remove Theme Image',
        'use_featured_image' => 'Use as Theme Image',
        'not_found_in_trash' => 'No Chatbot Theme found in Trash',
        'parent_item_colon' => 'Parent Chatbot Theme:',
        'menu_name' => 'Chatbot Themes',
        'item_published' => 'Post published.',
        'item_published_privately' => 'Post published privately.',
        'item_reverted_to_draft' => 'Post reverted to draft.',
        'item_trashed' => 'Post trashed.',
        'item_scheduled' => 'Post scheduled.',
        'item_updated' => 'Post updated.',
        'item_link' => 'Post Link',
        'item_link_description' => 'A link to a post.',
    );
    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'Chatbot Themes',
        'supports' => array( 'title', 'editor' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_themes', $args);

    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'AI User Data',
        'supports' => array( 'title', 'editor', 'custom-fields' ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => PHP_INT_MAX,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        )
    );
    $admin_caps = array('capabilities' => array(
        'edit_post'          => 'access_aiomatic_menu',
        'read_post'          => 'access_aiomatic_menu',
        'delete_post'        => 'access_aiomatic_menu',
        'edit_posts'         => 'access_aiomatic_menu',
        'edit_others_posts'  => 'access_aiomatic_menu',
        'delete_posts'       => 'access_aiomatic_menu',
        'publish_posts'      => 'access_aiomatic_menu',
        'read_private_posts' => 'access_aiomatic_menu'
    ));
    $args = array_merge($args, $admin_caps);
    register_post_type( 'aiomatic_user_data', $args);

    $labels = array(
        'name'                  => esc_html_x( 'Leads', 'Post Type General Name', 'aiomatic-automatic-ai-content-writer' ),
        'singular_name'         => esc_html_x( 'Lead', 'Post Type Singular Name', 'aiomatic-automatic-ai-content-writer' ),
        'menu_name'             => esc_html__( 'Leads', 'aiomatic-automatic-ai-content-writer' ),
        'name_admin_bar'        => esc_html__( 'Lead', 'aiomatic-automatic-ai-content-writer' ),
        'archives'              => esc_html__( 'Lead Archives', 'aiomatic-automatic-ai-content-writer' ),
        'attributes'            => esc_html__( 'Lead Attributes', 'aiomatic-automatic-ai-content-writer' ),
        'all_items'             => esc_html__( 'All Leads', 'aiomatic-automatic-ai-content-writer' ),
        'add_new_item'          => esc_html__( 'Add New Lead', 'aiomatic-automatic-ai-content-writer' ),
        'add_new'               => esc_html__( 'Add New', 'aiomatic-automatic-ai-content-writer' ),
        'new_item'              => esc_html__( 'New Lead', 'aiomatic-automatic-ai-content-writer' ),
        'edit_item'             => esc_html__( 'Edit Lead', 'aiomatic-automatic-ai-content-writer' ),
        'update_item'           => esc_html__( 'Update Lead', 'aiomatic-automatic-ai-content-writer' ),
        'view_item'             => esc_html__( 'View Lead', 'aiomatic-automatic-ai-content-writer' ),
        'view_items'            => esc_html__( 'View Leads', 'aiomatic-automatic-ai-content-writer' ),
        'search_items'          => esc_html__( 'Search Lead', 'aiomatic-automatic-ai-content-writer' ),
        'not_found'             => esc_html__( 'Not found', 'aiomatic-automatic-ai-content-writer' ),
        'not_found_in_trash'    => esc_html__( 'Not found in Trash', 'aiomatic-automatic-ai-content-writer' ),
    );
    $args = array(
        'label'                 => esc_html__( 'Lead', 'aiomatic-automatic-ai-content-writer' ),
        'description'           => esc_html__( 'Leads collected from chatbot', 'aiomatic-automatic-ai-content-writer' ),
        'hierarchical'          => false,
        'labels'                => $labels,
        'supports'              => array( 'title' ),
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => false,
        'show_in_nav_menus'     => false,
        'show_in_admin_bar'     => false,
        'has_archive'           => false,
        'publicly_queryable'    => false,
        'show_in_rest'          => false,
        'exclude_from_search'   => true,
        'query_var'             => true,
        'can_export'            => false,
        'rewrite'               => false,
        'menu_position'         => PHP_INT_MAX,
        'capability_type'       => 'post',
        'capabilities' => array(
            'edit_post'          => 'edit_post',
            'read_post'          => 'read_post',
            'delete_post'        => 'delete_post',
            'edit_posts'         => 'edit_posts',
            'edit_others_posts'  => 'edit_others_posts',
            'publish_posts'      => 'publish_posts',
            'read_private_posts' => 'read_private_posts',
            'create_posts'       => 'do_not_allow',
        ),
        'map_meta_cap' => true,
    );
    register_post_type( 'aiomatic_lead', $args );

    if ( function_exists( 'register_block_type' ) ) {
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-display', array(
            'render_callback' => 'aiomatic_display_posts_shortcode',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-list', array(
            'render_callback' => 'aiomatic_list_posts',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-article', array(
            'render_callback' => 'aiomatic_article',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-image', array(
            'render_callback' => 'aiomatic_image',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-stable-image', array(
            'render_callback' => 'aiomatic_stable_image',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-midjourney-image', array(
            'render_callback' => 'aiomatic_midjourney_image',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-replicate-image', array(
            'render_callback' => 'aiomatic_replicate_image',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-completion', array(
            'render_callback' => 'aiomatic_form_shortcode',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-editing', array(
            'render_callback' => 'aiomatic_edit_shortcode',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-image-generator', array(
            'render_callback' => 'aiomatic_image_shortcode',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-stable-image-generator', array(
            'render_callback' => 'aiomatic_stable_image_shortcode',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-midjourney-image-generator', array(
            'render_callback' => 'aiomatic_midjourney_image_shortcode',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-replicate-image-generator', array(
            'render_callback' => 'aiomatic_replicate_image_shortcode',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-chat', array(
            'render_callback' => 'aiomatic_chat_shortcode',
        ) );
        register_block_type( 'aiomatic-automatic-ai-content-writer/aiomatic-persona-selector', array(
            'render_callback' => 'aiomatic_persona_shortcode',
        ) );
    }
    if(!taxonomy_exists('coderevolution_post_source'))
    {
        $labels = array(
            'name' => esc_html_x('Post Source', 'taxonomy general name', 'aiomatic-automatic-ai-content-writer'),
            'singular_name' => esc_html_x('Post Source', 'taxonomy singular name', 'aiomatic-automatic-ai-content-writer'),
            'search_items' => esc_html__('Search Post Source', 'aiomatic-automatic-ai-content-writer'),
            'popular_items' => esc_html__('Popular Post Source', 'aiomatic-automatic-ai-content-writer'),
            'all_items' => esc_html__('All Post Sources', 'aiomatic-automatic-ai-content-writer'),
            'parent_item' => null,
            'parent_item_colon' => null,
            'edit_item' => esc_html__('Edit Post Source', 'aiomatic-automatic-ai-content-writer'),
            'update_item' => esc_html__('Update Post Source', 'aiomatic-automatic-ai-content-writer'),
            'add_new_item' => esc_html__('Add New Post Source', 'aiomatic-automatic-ai-content-writer'),
            'new_item_name' => esc_html__('New Post Source Name', 'aiomatic-automatic-ai-content-writer'),
            'separate_items_with_commas' => esc_html__('Separate Post Source with commas', 'aiomatic-automatic-ai-content-writer'),
            'add_or_remove_items' => esc_html__('Add or remove Post Source', 'aiomatic-automatic-ai-content-writer'),
            'choose_from_most_used' => esc_html__('Choose from the most used Post Source', 'aiomatic-automatic-ai-content-writer'),
            'not_found' => esc_html__('No Post Sources found.', 'aiomatic-automatic-ai-content-writer'),
            'menu_name' => esc_html__('Post Source', 'aiomatic-automatic-ai-content-writer')
        );
        
        $args = array(
            'hierarchical' => false,
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'description' => 'Post Source',
            'labels' => $labels,
            'show_admin_column' => true,
            'update_count_callback' => '_update_post_term_count',
            'rewrite' => false
        );
        
        $add_post_type = array(
            'post',
            'page'
        );
        $xargs = array(
            'public'   => true,
            '_builtin' => false
        );
        $output = 'names'; 
        $operator = 'and';
        $post_types = get_post_types( $xargs, $output, $operator );
        if ( $post_types ) 
        {
            foreach ( $post_types  as $post_type ) {
                $add_post_type[] = $post_type;
            }
        }
        register_taxonomy('coderevolution_post_source', $add_post_type, $args);
        add_action('pre_get_posts', function($qry) {
            if (is_admin()) return;
            if (is_tax('coderevolution_post_source')){
                $qry->set_404();
            }
        });
    }
}
function aiomatic_set_custom_edit_lead_columns($columns) 
{
    $columns = array(
        'cb'           => '<input type="checkbox" />',
        'title'        => esc_html__('Email', 'aiomatic-automatic-ai-content-writer'),
        'name'         => esc_html__('Name', 'aiomatic-automatic-ai-content-writer'),
        'phone_number' => esc_html__('Phone Number', 'aiomatic-automatic-ai-content-writer'),
        'company_name' => esc_html__('Company', 'aiomatic-automatic-ai-content-writer'),
        'job_title'    => esc_html__('Job Title', 'aiomatic-automatic-ai-content-writer'),
        'location'     => esc_html__('Location', 'aiomatic-automatic-ai-content-writer'),
        'date'         => esc_html__('Date', 'aiomatic-automatic-ai-content-writer'),
    );
    return $columns;
}
add_filter('manage_lead_posts_columns', 'aiomatic_set_custom_edit_lead_columns');
function aiomatic_custom_lead_column( $column, $post_id ) 
{
    switch ( $column ) 
    {
        case 'name' :
            echo esc_html( get_post_meta( $post_id, 'name', true ) );
            break;
        case 'phone_number' :
            echo esc_html( get_post_meta( $post_id, 'phone_number', true ) );
            break;
        case 'company_name' :
            echo esc_html( get_post_meta( $post_id, 'company_name', true ) );
            break;
        case 'job_title' :
            echo esc_html( get_post_meta( $post_id, 'job_title', true ) );
            break;
        case 'location' :
            echo esc_html( get_post_meta( $post_id, 'location', true ) );
            break;
    }
}
add_action( 'manage_lead_posts_custom_column', 'aiomatic_custom_lead_column', 10, 2 );
function aiomatic_lead_info_callback( $post ) 
{
    $fields = array(
        'name',
        'phone_number',
        'job_title',
        'company_name',
        'location',
        'birth_date',
        'how_you_found_us',
        'website_url',
        'preferred_contact_method',
    );
    echo '<table class="form-table">';
    foreach ( $fields as $field ) {
        $value = get_post_meta( $post->ID, $field, true );
        echo '<tr>';
        echo '<th><label for="'.esc_attr($field).'">'.esc_html( ucwords( str_replace( '_', ' ', $field ) ) ).'</label></th>';
        echo '<td><input type="text" name="'.esc_attr($field).'" id="'.esc_attr($field).'" value="'.esc_attr( $value ).'" class="regular-text" readonly /></td>';
        echo '</tr>';
    }
    echo '</table>';
}
function aiomatic_add_lead_metaboxes() {
    add_meta_box(
        'lead_info',
        esc_html__('Lead Information', 'aiomatic-automatic-ai-content-writer'),
        'aiomatic_lead_info_callback',
        'aiomatic_lead',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'aiomatic_add_lead_metaboxes' );
add_action('add_meta_boxes', 'aiomatic_add_persona_first_message_meta_box');
function aiomatic_add_persona_first_message_meta_box() {
    add_meta_box(
        'persona_first_message_id',
        'AI Persona First Message',
        'aiomatic_render_persona_first_message_meta_box',
        'aiomatic_personas',
        'normal',
        'high'
    );
}

add_action('add_meta_boxes', 'aiomatic_add_assistant_first_message_meta_box');
function aiomatic_add_assistant_first_message_meta_box() {
    add_meta_box(
        'assistant_first_message_id',
        'AI Assistant First Message',
        'aiomatic_render_assistant_first_message_meta_box',
        'aiomatic_assistants',
        'normal',
        'high'
    );
}
function aiomatic_render_persona_first_message_meta_box($post) 
{
    $custom_text = get_post_meta($post->ID, '_persona_first_message', true);
    ?>
    <textarea rows="2" id="persona_first_message" placeholder="<?php echo esc_html__('AI Persona First Message','aiomatic-automatic-ai-content-writer')?>" class="widefat" name="persona_first_message"><?php echo esc_textarea($custom_text);?></textarea>
    <?php
}
function aiomatic_render_assistant_first_message_meta_box($post) 
{
    $custom_text = get_post_meta($post->ID, '_assistant_first_message', true);
    ?>
    <textarea rows="2" id="assistant_first_message" placeholder="<?php echo esc_html__('AI Assistant First Message','aiomatic-automatic-ai-content-writer')?>" class="widefat" name="assistant_first_message"><?php echo esc_textarea($custom_text);?></textarea>
    <?php
}
add_action('save_post', 'aiomatic_save_persona_first_message');
function aiomatic_save_persona_first_message($post_id) 
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
    {
        return;
    }
    if(get_post_type($post_id) !== 'aiomatic_personas')
    {
        return;
    }
    if (isset($_POST['persona_first_message'])) {
        update_post_meta($post_id, '_persona_first_message', sanitize_text_field($_POST['persona_first_message']));
    }
}
add_action('save_post', 'aiomatic_save_assistant_first_message');
function aiomatic_save_assistant_first_message($post_id) 
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
    {
        return;
    }
    if(get_post_type($post_id) !== 'aiomatic_assistants')
    {
        return;
    }
    if (isset($_POST['assistant_first_message'])) {
        update_post_meta($post_id, '_assistant_first_message', sanitize_text_field($_POST['assistant_first_message']));
    }
}
function aiomatic_change_excerpt( $translated_text, $text, $domain ) 
{
    global $post;
    if ( is_admin() && $post ) 
    {
        if('aiomatic_personas' === $post->post_type)
        {
            if ( $text === 'Excerpt' ) {
                $translated_text = 'Persona Role';
            }
            if ( $text === 'Add Title' ) {
                $translated_text = 'Add Persona Name';
            }
        }
        elseif('aiomatic_assistants' === $post->post_type)
        {
            if ( $text === 'Excerpt' ) {
                $translated_text = 'Assistant Role';
            }
            if ( $text === 'Add Title' ) {
                $translated_text = 'Add Assistant Name';
            }
        }
    }
    return $translated_text;
}
add_filter( 'gettext', 'aiomatic_change_excerpt', 20, 3 );

add_action( 'current_screen', function() {
    $embeddings_post_type = 'aiomatic_embeddings';
    $forms_post_type = 'aiomatic_forms';
    $persona_post_type = 'aiomatic_personas';
    $assistant_post_type = 'aiomatic_assistants';
    $batch_post_type = 'aiomatic_batches';
    $omni_post_type = 'aiomatic_omni_temp';
    $editor_post_type = 'aiomatic_editor_temp';
    $theme_post_type = 'aiomatic_themes';
    $screen = get_current_screen();
    global $pagenow;
    if ( ! in_array( $pagenow, array( 'post-new.php' ), true )
         && 'post' === $screen->base
         && ($batch_post_type === $screen->post_type || $embeddings_post_type === $screen->post_type || $forms_post_type === $screen->post_type || $persona_post_type === $screen->post_type || $omni_post_type === $screen->post_type || $editor_post_type === $screen->post_type || $assistant_post_type === $screen->post_type || $theme_post_type === $screen->post_type) ) 
    {
        add_action( 'admin_footer', 'aiomatic_hide_batch_update_buttons' );
    }

});
add_filter('post_updated_messages', 'aiomatic_contact_updated_messages');
function aiomatic_contact_updated_messages( $messages ) 
{
    if($GLOBALS['post']->post_type == 'aiomatic_embeddings')
    {
        $messages['aiomatic_embeddings'] = array(
            0 => '',
            1 => __('Embedding updated.'),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('Embedding updated.'),
            5 => isset($_GET['revision']) ? sprintf( __('Embedding restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => __('Embedding published.'),
            7 => __('Embedding saved.'),
            8 => __('Embedding submitted.'),
            9 => __('Embedding scheduled for: <strong>%1$s</strong>.'),
            10 => __('Embedding draft updated.')
        );
    }
    elseif($GLOBALS['post']->post_type == 'aiomatic_forms')
    {
        $messages['aiomatic_forms'] = array(
            0 => '',
            1 => __('Form updated.'),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('Form updated.'),
            5 => isset($_GET['revision']) ? sprintf( __('Form restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => __('Form published.'),
            7 => __('Form saved.'),
            8 => __('Form submitted.'),
            9 => __('Form scheduled for: <strong>%1$s</strong>.'),
            10 => __('Form draft updated.')
        );
    }
    elseif($GLOBALS['post']->post_type == 'aiomatic_personas')
    {
        $messages['aiomatic_personas'] = array(
            0 => '',
            1 => __('Persona updated.'),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('Persona updated.'),
            5 => isset($_GET['revision']) ? sprintf( __('Persona restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => __('Persona published.'),
            7 => __('Persona saved.'),
            8 => __('Persona submitted.'),
            9 => __('Persona scheduled for: <strong>%1$s</strong>.'),
            10 => __('Persona draft updated.')
        );
    }
    elseif($GLOBALS['post']->post_type == 'aiomatic_assistants')
    {
        $messages['aiomatic_assistants'] = array(
            0 => '',
            1 => __('Assistant updated.'),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('Assistant updated.'),
            5 => isset($_GET['revision']) ? sprintf( __('Assistant restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => __('Assistant published.'),
            7 => __('Assistant saved.'),
            8 => __('Assistant submitted.'),
            9 => __('Assistant scheduled for: <strong>%1$s</strong>.'),
            10 => __('Assistant draft updated.')
        );
    }
    elseif($GLOBALS['post']->post_type == 'aiomatic_batches')
    {
        $messages['aiomatic_batches'] = array(
            0 => '',
            1 => __('AI Batch Request updated.'),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('AI Batch Request updated.'),
            5 => isset($_GET['revision']) ? sprintf( __('AI Batch Request restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => __('AI Batch Request published.'),
            7 => __('AI Batch Request saved.'),
            8 => __('AI Batch Request submitted.'),
            9 => __('AI Batch Request scheduled for: <strong>%1$s</strong>.'),
            10 => __('AI Batch Request draft updated.')
        );
    }
    elseif($GLOBALS['post']->post_type == 'aiomatic_omni_temp')
    {
        $messages['aiomatic_omni_temp'] = array(
            0 => '',
            1 => __('OmniBlock Template updated.'),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('OmniBlock Template updated.'),
            5 => isset($_GET['revision']) ? sprintf( __('OmniBlock Template restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => __('OmniBlock Template published.'),
            7 => __('OmniBlock Template saved.'),
            8 => __('OmniBlock Template submitted.'),
            9 => __('OmniBlock Template scheduled for: <strong>%1$s</strong>.'),
            10 => __('OmniBlock Template draft updated.')
        );
    }
    elseif($GLOBALS['post']->post_type == 'aiomatic_editor_temp')
    {
        $messages['aiomatic_editor_temp'] = array(
            0 => '',
            1 => __('AI Content Editor Template updated.'),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('AI Content Editor Template updated.'),
            5 => isset($_GET['revision']) ? sprintf( __('AI Content Editor Template restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => __('AI Content Editor Template published.'),
            7 => __('AI Content Editor Template saved.'),
            8 => __('AI Content Editor Template submitted.'),
            9 => __('AI Content Editor Template scheduled for: <strong>%1$s</strong>.'),
            10 => __('AI Content Editor Template draft updated.')
        );
    }
    elseif($GLOBALS['post']->post_type == 'aiomatic_themes')
    {
        $messages['aiomatic_themes'] = array(
            0 => '',
            1 => __('Theme updated.'),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => __('Theme updated.'),
            5 => isset($_GET['revision']) ? sprintf( __('Theme restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => __('Theme published.'),
            7 => __('Theme saved.'),
            8 => __('Theme submitted.'),
            9 => __('Theme scheduled for: <strong>%1$s</strong>.'),
            10 => __('Theme draft updated.')
        );
    }
    return $messages;
}
function aiomatic_hide_batch_update_buttons() {
	?>
	<script type="text/javascript">
	(function( $ ) {
		'use strict';
		$('#submitdiv .edit-post-status').remove();
		$('#submitdiv .edit-visibility').remove();
		$('#submitdiv .edit-timestamp').remove();
		$('#minor-publishing-actions').remove();
		$('#delete-action').remove();
		$('#aiomatic_meta_box_function_add').remove();
		$('#aiomatic_meta_box_function_add').remove();
		$('#wp-content-media-buttons').remove();
	})( jQuery );
	</script>
	<?php
}

add_action('wp_loaded', 'aiomatic_run_cron', 0);
function aiomatic_run_cron()
{
    aiomatic_add_bulk_actions_to_all_post_types();
    if(isset($_GET['run_aiomatic_edit']))
    {
        $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
        if(isset($aiomatic_Spinner_Settings['auto_edit']) && $aiomatic_Spinner_Settings['auto_edit'] == 'external')
        {
            if(isset($aiomatic_Spinner_Settings['secret_word']) && $_GET['run_aiomatic_edit'] == urlencode($aiomatic_Spinner_Settings['secret_word']))
            {
                aiomatic_do_bulk_post();
                die();
            }
        }
    }
}
function aiomatic_disable_create_newpost() {
    global $wp_post_types;
    if(isset($wp_post_types['aiomatic_embeddings']->cap))
    {
        $wp_post_types['aiomatic_embeddings']->cap->create_posts = 'do_not_allow';
    }
    if(isset($wp_post_types['aiomatic_embeddings']->cap))
    {
        $wp_post_types['aiomatic_forms']->cap->create_posts = 'do_not_allow';
    }
    if(isset($wp_post_types['aiomatic_embeddings']->cap))
    {
        $wp_post_types['aiomatic_personas']->cap->create_posts = 'do_not_allow';
    }
    if(isset($wp_post_types['aiomatic_embeddings']->cap))
    {
        $wp_post_types['aiomatic_assistants']->cap->create_posts = 'do_not_allow';
    }
    if(isset($wp_post_types['aiomatic_embeddings']->cap))
    {
        $wp_post_types['aiomatic_batches']->cap->create_posts = 'do_not_allow';
    }
    if(isset($wp_post_types['aiomatic_embeddings']->cap))
    {
        $wp_post_types['aiomatic_omni_temp']->cap->create_posts = 'do_not_allow';
    }
    if(isset($wp_post_types['aiomatic_embeddings']->cap))
    {
        $wp_post_types['aiomatic_editor_temp']->cap->create_posts = 'do_not_allow';
    }
    if(isset($wp_post_types['aiomatic_embeddings']->cap))
    {
        $wp_post_types['aiomatic_themes']->cap->create_posts = 'do_not_allow';
    }
}
add_action('init','aiomatic_disable_create_newpost');
function aiomatic_embeddings_result($aiomatic_message, $token, $embedding_namespace = '')
{
    $result = array('status' => 'error','data' => '');
    $embeddingspresent = get_posts( 
        [
            'post_type' => 'aiomatic_embeddings', 
            'posts_per_page' => 1,
            'fields' => 'ids'
        ] 
    );
    
    if (empty($embeddingspresent)) 
    {
        $result['data'] = 'No embeddings are added in the plugin config!';
        return $result;
    };
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if ((!isset($aiomatic_Main_Settings['embeddings_api']) || trim($aiomatic_Main_Settings['embeddings_api']) == '') || (isset($aiomatic_Main_Settings['embeddings_api']) && trim($aiomatic_Main_Settings['embeddings_api']) == 'pinecone'))
    {
        if (!isset($aiomatic_Main_Settings['pinecone_app_id']) || trim($aiomatic_Main_Settings['pinecone_app_id']) == '') 
        {
            $result['data'] = 'Pinecone API key needed in plugin settings.';
            return $result;
        }
        if (!isset($aiomatic_Main_Settings['pinecone_index']) || trim($aiomatic_Main_Settings['pinecone_index']) == '') 
        {
            $result['data'] = 'Pinecone Index neededs to be added in plugin settings.';
            return $result;
        }
        if (!isset($aiomatic_Main_Settings['app_id']) || trim($aiomatic_Main_Settings['app_id']) == '') 
        {
            $result['data'] = 'OpenAI/AiomaticAPI API key needed in plugin settings.';
            return $result;
        }
        if (isset($aiomatic_Main_Settings['embeddings_model']) && trim($aiomatic_Main_Settings['embeddings_model']) != '') 
        {
            $embeddings_model = trim($aiomatic_Main_Settings['embeddings_model']);
        }
        else
        {
            $embeddings_model = AIOMATIC_DEFAULT_MODEL_EMBEDDING;
        }
        if (isset($aiomatic_Main_Settings['pinecone_topk']) && trim($aiomatic_Main_Settings['pinecone_topk']) != '') 
        {
            $pinecone_topk = intval(trim($aiomatic_Main_Settings['pinecone_topk']));
            if($pinecone_topk < 1 || $pinecone_topk > 10000)
            {
                $pinecone_topk = 1;
            }
        }
        else
        {
            $pinecone_topk = 1;
        }
        if(empty($result['data'])) 
        {
            $session = aiomatic_get_session_id();
            $maxResults = 1;
            $query = new Aiomatic_Query($aiomatic_message, 2048, $embeddings_model, 0, '', 'embeddings', 'embeddings', $token, $session, $maxResults, '', '');
            $delay = '';
            if (isset($aiomatic_Main_Settings['request_delay']) && $aiomatic_Main_Settings['request_delay'] != '') 
            {
                if(stristr($aiomatic_Main_Settings['request_delay'], ',') !== false)
                {
                    $tempo = explode(',', $aiomatic_Main_Settings['request_delay']);
                    if(isset($tempo[1]) && is_numeric(trim($tempo[1])) && is_numeric(trim($tempo[0])))
                    {
                        $delay = rand(trim($tempo[0]), trim($tempo[1]));
                    }
                }
                else
                {
                    if(is_numeric(trim($aiomatic_Main_Settings['request_delay'])))
                    {
                        $delay = intval(trim($aiomatic_Main_Settings['request_delay']));
                    }
                }
            }
            if($delay != '' && is_numeric($delay))
            {
                usleep($delay);
            }
            if(aiomatic_is_ollama_embeddings_model($embeddings_model))
            {
                $error = '';
                $response = aiomatic_generate_embeddings_ollama($embeddings_model, $aiomatic_message, $error);
                if($response === false)
                {
                    $result['data'] = 'Failed to call Embeddings API: ' . $error;
                    return $result;
                }
                if(isset($response['error']))
                {
                    $result['data'] = 'Error while processing AI response: ' . $response['error'];
                    return $result;
                }
                $embedding = $response;
                $response = apply_filters( 'aiomatic_embeddings_reply_raw', $response, $aiomatic_message );
                apply_filters( 'aiomatic_ai_reply', $response, $query );
                if (!empty($embedding)) {
                    $headers = array(
                        'Content-Type' => 'application/json',
                        'Api-Key' => trim($aiomatic_Main_Settings['pinecone_app_id'])
                    );
                    $pine_arr = array(
                        'vector' => $embedding,
                        'topK' => $pinecone_topk
                    );
                    if(!empty($embedding_namespace)){
                        $pine_arr['namespace'] = $embedding_namespace;
                    }
                    else
                    {
                        if (isset($aiomatic_Main_Settings['pinecone_namespace']) && trim($aiomatic_Main_Settings['pinecone_namespace']) != '')
                        {
                            $pine_arr['namespace'] = trim($aiomatic_Main_Settings['pinecone_namespace']);
                        }
                    }
                    $response = wp_remote_post('https://' . preg_replace("(^https?:\/\/)", "", trim($aiomatic_Main_Settings['pinecone_index'] )) . '/query', array(
                        'headers' => $headers,
                        'body' => json_encode($pine_arr),
                        'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
                    ));
                    if (is_wp_error($response)) {
                        $result['data'] = esc_html($response->get_error_message());
                    } else {
                        $body = json_decode($response['body'], true);
                        if ($body) {
                            if (isset($body['matches']) && is_array($body['matches']) && count($body['matches'])) 
                            {
                                $data = '';
                                $found = false;
                                foreach($body['matches'] as $match){
                                    $aiomatic_embedding = get_post($match['id']);
                                    if ($aiomatic_embedding) {
                                        $data .= empty($data) ? $aiomatic_embedding->post_content : "\n" . $aiomatic_embedding->post_content;
                                        $found = true;
                                    }
                                }
                                if($found == true)
                                {
                                    $result['data'] = $data;
                                    $result['status'] = 'success';
                                }
                                else
                                {
                                    $result['data'] = 'No results found';
                                }
                            }
                        }
                    }
                }
            }
            elseif(aiomatic_google_extension_is_google_embeddings_model($embeddings_model))
            {
                $error = '';
                $response = aiomatic_generate_embeddings_google($embeddings_model, $aiomatic_message, $error);
                if($response === false)
                {
                    $result['msg'] = 'Failed to call Embeddings API: ' . $error;
                    return $result;
                }
                if(isset($response['error']))
                {
                    $result['msg'] = 'Error while processing AI response: ' . $response['error'];
                    return $result;
                }
                $embedding = $response;
                $response = apply_filters( 'aiomatic_embeddings_reply_raw', $response, $aiomatic_message );
                apply_filters( 'aiomatic_ai_reply', $response, $query );
                if (!empty($embedding)) {
                    $headers = array(
                        'Content-Type' => 'application/json',
                        'Api-Key' => trim($aiomatic_Main_Settings['pinecone_app_id'])
                    );
                    $pine_arr = array(
                        'vector' => $embedding,
                        'topK' => $pinecone_topk
                    );
                    if(!empty($embedding_namespace)){
                        $pine_arr['namespace'] = $embedding_namespace;
                    }
                    else
                    {
                        if (isset($aiomatic_Main_Settings['pinecone_namespace']) && trim($aiomatic_Main_Settings['pinecone_namespace']) != '')
                        {
                            $pine_arr['namespace'] = trim($aiomatic_Main_Settings['pinecone_namespace']);
                        }
                    }
                    $response = wp_remote_post('https://' . preg_replace("(^https?:\/\/)", "", trim($aiomatic_Main_Settings['pinecone_index'] )) . '/query', array(
                        'headers' => $headers,
                        'body' => json_encode($pine_arr),
                        'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
                    ));
                    if (is_wp_error($response)) {
                        $result['data'] = esc_html($response->get_error_message());
                    } else {
                        $body = json_decode($response['body'], true);
                        if ($body) {
                            if (isset($body['matches']) && is_array($body['matches']) && count($body['matches'])) 
                            {
                                $data = '';
                                $found = false;
                                foreach($body['matches'] as $match){
                                    $aiomatic_embedding = get_post($match['id']);
                                    if ($aiomatic_embedding) {
                                        $data .= empty($data) ? $aiomatic_embedding->post_content : "\n" . $aiomatic_embedding->post_content;
                                        $found = true;
                                    }
                                }
                                if($found == true)
                                {
                                    $result['data'] = $data;
                                    $result['status'] = 'success';
                                }
                                else
                                {
                                    $result['data'] = 'No results found';
                                }
                            }
                        }
                    }
                }
            }
            else
            {
                if(aiomatic_is_aiomaticapi_key($token))
                {
                    $error = '';
                    $response = aiomatic_embeddings_aiomaticapi($token, $embeddings_model, $aiomatic_message, 0, $error);
                    if($response === false)
                    {
                        $result['data'] = 'Failed to call Embeddings API: ' . $error;
                        return $result;
                    }
                    if(isset($response->error))
                    {
                        $result['data'] = 'Error while processing AI response: ' . $response->error;
                        return $result;
                    }
                    if(!isset($response[0]->embedding))
                    {
                        $result['data'] = 'Failed to call Embeddings API: ' . print_r($response, true);
                        return $result;
                    }
                    $response = apply_filters( 'aiomatic_embeddings_reply_raw', $response, $aiomatic_message );
                    apply_filters( 'aiomatic_ai_reply', $response, $query );
                    $embedding = $response[0]->embedding;
                    if (!empty($embedding)) {
                        $headers = array(
                            'Content-Type' => 'application/json',
                            'Api-Key' => trim($aiomatic_Main_Settings['pinecone_app_id'])
                        );
                        $pine_arr = array(
                            'vector' => $embedding,
                            'topK' => $pinecone_topk
                        );
                        if(!empty($embedding_namespace)){
                            $pine_arr['namespace'] = $embedding_namespace;
                        }
                        else
                        {
                            if (isset($aiomatic_Main_Settings['pinecone_namespace']) && trim($aiomatic_Main_Settings['pinecone_namespace']) != '')
                            {
                                $pine_arr['namespace'] = trim($aiomatic_Main_Settings['pinecone_namespace']);
                            }
                        }
                        $response = wp_remote_post('https://' . preg_replace("(^https?:\/\/)", "", trim($aiomatic_Main_Settings['pinecone_index'] )) . '/query', array(
                            'headers' => $headers,
                            'body' => json_encode($pine_arr),
                            'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
                        ));
                        if (is_wp_error($response)) {
                            $result['data'] = esc_html($response->get_error_message());
                        } else {
                            $body = json_decode($response['body'], true);
                            if ($body) {
                                if (isset($body['matches']) && is_array($body['matches']) && count($body['matches'])) 
                                {
                                    $data = '';
                                    $found = false;
                                    foreach($body['matches'] as $match){
                                        $aiomatic_embedding = get_post($match['id']);
                                        if ($aiomatic_embedding) {
                                            $data .= empty($data) ? $aiomatic_embedding->post_content : "\n" . $aiomatic_embedding->post_content;
                                            $found = true;
                                        }
                                    }
                                    if($found == true)
                                    {
                                        $result['data'] = $data;
                                        $result['status'] = 'success';
                                    }
                                    else
                                    {
                                        $result['data'] = 'No results found';
                                    }
                                }
                            }
                        }
                    }
                }
                elseif(aiomatic_check_if_azure($aiomatic_Main_Settings))
                {
                    $error = '';
                    $response = aiomatic_embeddings_azure($token, $embeddings_model, $aiomatic_message, 0, $error);
                    if($response === false)
                    {
                        $result['data'] = 'Failed to call Embeddings API: ' . $error;
                        return $result;
                    }
                    if(isset($response->error))
                    {
                        $result['data'] = 'Error while processing AI response: ' . $response->error;
                        return $result;
                    }
                    if(!isset($response[0]->embedding))
                    {
                        $result['data'] = 'Failed to call Embeddings API: ' . print_r($response, true);
                        return $result;
                    }
                    $response = apply_filters( 'aiomatic_embeddings_reply_raw', $response, $aiomatic_message );
                    apply_filters( 'aiomatic_ai_reply', $response, $query );
                    $embedding = $response[0]->embedding;
                    if (!empty($embedding)) {
                        $headers = array(
                            'Content-Type' => 'application/json',
                            'Api-Key' => trim($aiomatic_Main_Settings['pinecone_app_id'])
                        );
                        $pine_arr = array(
                            'vector' => $embedding,
                            'topK' => $pinecone_topk
                        );
                        if(!empty($embedding_namespace)){
                            $pine_arr['namespace'] = $embedding_namespace;
                        }
                        else
                        {
                            if (isset($aiomatic_Main_Settings['pinecone_namespace']) && trim($aiomatic_Main_Settings['pinecone_namespace']) != '')
                            {
                                $pine_arr['namespace'] = trim($aiomatic_Main_Settings['pinecone_namespace']);
                            }
                        }
                        $response = wp_remote_post('https://' . preg_replace("(^https?:\/\/)", "", trim($aiomatic_Main_Settings['pinecone_index'])) . '/query', array(
                            'headers' => $headers,
                            'body' => json_encode($pine_arr),
                            'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
                        ));
                        if (is_wp_error($response)) {
                            $result['data'] = esc_html($response->get_error_message());
                        } else {
                            $body = json_decode($response['body'], true);
                            if ($body) {
                                if (isset($body['matches']) && is_array($body['matches']) && count($body['matches'])) 
                                {
                                    $data = '';
                                    $found = false;
                                    foreach($body['matches'] as $match){
                                        $aiomatic_embedding = get_post($match['id']);
                                        if ($aiomatic_embedding) {
                                            $data .= empty($data) ? $aiomatic_embedding->post_content : "\n" . $aiomatic_embedding->post_content;
                                            $found = true;
                                        }
                                    }
                                    if($found == true)
                                    {
                                        $result['data'] = $data;
                                        $result['status'] = 'success';
                                    }
                                    else
                                    {
                                        $result['data'] = 'No results found';
                                    }
                                }
                            }
                        }
                    }
                }
                else
                {
                    require_once (dirname(__FILE__) . "/res/openai/Url.php"); 
                    require_once (dirname(__FILE__) . "/res/openai/OpenAi.php");
                    $open_ai = new OpenAi($token);
                    if (isset($aiomatic_Main_Settings['openai_organization']) && $aiomatic_Main_Settings['openai_organization'] != '')
                    {
                        $open_ai->setORG($aiomatic_Main_Settings['openai_organization']);
                    }
                    $response = $open_ai->embeddings([
                        'input' => $aiomatic_message,
                        'model' => $embeddings_model
                    ]);
                    $response = json_decode($response, true);
                    if (isset($response['error']) && !empty($response['error'])) {
                        $result['data'] = $response['error']['message'];
                    } 
                    else 
                    {
                        $response = apply_filters( 'aiomatic_embeddings_reply_raw', (object)$response, $aiomatic_message );
                        $response = (array) $response;
                        apply_filters( 'aiomatic_ai_reply', $response, $query );
                        $embedding = $response['data'][0]['embedding'];
                        if (!empty($embedding)) {
                            $headers = array(
                                'Content-Type' => 'application/json',
                                'Api-Key' => trim($aiomatic_Main_Settings['pinecone_app_id'])
                            );
                            $pine_arr = array(
                                'vector' => $embedding,
                                'topK' => $pinecone_topk
                            );
                            if(!empty($embedding_namespace)){
                                $pine_arr['namespace'] = $embedding_namespace;
                            }
                            else
                            {
                                if (isset($aiomatic_Main_Settings['pinecone_namespace']) && trim($aiomatic_Main_Settings['pinecone_namespace']) != '')
                                {
                                    $pine_arr['namespace'] = trim($aiomatic_Main_Settings['pinecone_namespace']);
                                }
                            }
                            $response = wp_remote_post('https://' . preg_replace("(^https?:\/\/)", "", trim($aiomatic_Main_Settings['pinecone_index'])) . '/query', array(
                                'headers' => $headers,
                                'body' => json_encode($pine_arr),
                                'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
                            ));
                            if (is_wp_error($response)) {
                                $result['data'] = esc_html($response->get_error_message());
                            } else {
                                $body = json_decode($response['body'], true);
                                if ($body) {
                                    if (isset($body['matches']) && is_array($body['matches']) && count($body['matches'])) 
                                    {
                                        $data = '';
                                        $found = false;
                                        foreach($body['matches'] as $match){
                                            $aiomatic_embedding = get_post($match['id']);
                                            if ($aiomatic_embedding) {
                                                $data .= empty($data) ? $aiomatic_embedding->post_content : "\n" . $aiomatic_embedding->post_content;
                                                $found = true;
                                            }
                                        }
                                        if($found == true)
                                        {
                                            $result['data'] = $data;
                                            $result['status'] = 'success';
                                        }
                                        else
                                        {
                                            $result['data'] = 'No results found';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    elseif(isset($aiomatic_Main_Settings['embeddings_api']) && trim($aiomatic_Main_Settings['embeddings_api']) == 'qdrant')
    {
        if (!isset($aiomatic_Main_Settings['qdrant_app_id']) || trim($aiomatic_Main_Settings['qdrant_app_id']) == '') 
        {
            $result['data'] = 'Qdrant API key needed in plugin settings.';
            return $result;
        }
        if (!isset($aiomatic_Main_Settings['qdrant_index']) || trim($aiomatic_Main_Settings['qdrant_index']) == '') 
        {
            $result['data'] = 'Qdrant Index neededs to be added in plugin settings.';
            return $result;
        }
        if (!isset($aiomatic_Main_Settings['app_id']) || trim($aiomatic_Main_Settings['app_id']) == '') 
        {
            $result['data'] = 'OpenAI/AiomaticAPI API key needed in plugin settings.';
            return $result;
        }
        if (isset($aiomatic_Main_Settings['embeddings_model']) && trim($aiomatic_Main_Settings['embeddings_model']) != '') 
        {
            $embeddings_model = trim($aiomatic_Main_Settings['embeddings_model']);
        }
        else
        {
            $embeddings_model = AIOMATIC_DEFAULT_MODEL_EMBEDDING;
        }
        if (isset($aiomatic_Main_Settings['pinecone_topk']) && trim($aiomatic_Main_Settings['pinecone_topk']) != '') 
        {
            $pinecone_topk = intval(trim($aiomatic_Main_Settings['pinecone_topk']));
            if($pinecone_topk < 1 || $pinecone_topk > 10000)
            {
                $pinecone_topk = 1;
            }
        }
        else
        {
            $pinecone_topk = 1;
        }
        $aiomatic_qdrant_api = trim($aiomatic_Main_Settings['qdrant_app_id']);
        $aiomatic_qdrant_environment = rtrim(trim($aiomatic_Main_Settings['qdrant_index'], '/'));
        $aiomatic_qdrant_environment = preg_replace("(^https?:\/\/)", "", $aiomatic_qdrant_environment);
        $qdrant_url = 'https://' . $aiomatic_qdrant_environment;
        if(empty($embedding_namespace))
        {
            if (isset($aiomatic_Main_Settings['qdrant_name']) && trim($aiomatic_Main_Settings['qdrant_name']) != '')
            {
                $index_name = $aiomatic_Main_Settings['qdrant_name'];
            }
            else
            {
                $index_name = 'qdrant';
            }
        }
        else
        {
            $index_name = trim($embedding_namespace);
        }
        if(empty($result['data'])) 
        {
            $session = aiomatic_get_session_id();
            $maxResults = 1;
            $query = new Aiomatic_Query($aiomatic_message, 2048, $embeddings_model, 0, '', 'embeddings', 'embeddings', $token, $session, $maxResults, '', '');
            $delay = '';
            if (isset($aiomatic_Main_Settings['request_delay']) && $aiomatic_Main_Settings['request_delay'] != '') 
            {
                if(stristr($aiomatic_Main_Settings['request_delay'], ',') !== false)
                {
                    $tempo = explode(',', $aiomatic_Main_Settings['request_delay']);
                    if(isset($tempo[1]) && is_numeric(trim($tempo[1])) && is_numeric(trim($tempo[0])))
                    {
                        $delay = rand(trim($tempo[0]), trim($tempo[1]));
                    }
                }
                else
                {
                    if(is_numeric(trim($aiomatic_Main_Settings['request_delay'])))
                    {
                        $delay = intval(trim($aiomatic_Main_Settings['request_delay']));
                    }
                }
            }
            if($delay != '' && is_numeric($delay))
            {
                usleep($delay);
            }
            
            if(aiomatic_is_ollama_embeddings_model($embeddings_model))
            {
                $error = '';
                $response = aiomatic_generate_embeddings_ollama($embeddings_model, $aiomatic_message, $error);
                if($response === false)
                {
                    $result['data'] = 'Failed to call Embeddings API: ' . $error;
                    return $result;
                }
                if(isset($response['error']))
                {
                    $result['data'] = 'Error while processing AI response: ' . $response['error'];
                    return $result;
                }
                $embedding = $response;
                $response = apply_filters( 'aiomatic_embeddings_reply_raw', $response, $aiomatic_message );
                apply_filters( 'aiomatic_ai_reply', $response, $query );
                if (!empty($embedding)) 
                {
                    require_once (dirname(__FILE__) . "/res/Qdrant.php");
                    $found_vectors = aiomatic_qdrant_query_vectors(trim($aiomatic_qdrant_api), $qdrant_url, $index_name, $pinecone_topk, $embedding );
                    $data = '';
                    $found = false;
                    foreach($found_vectors as $fv)
                    {
                        $args = array(
                            'post_type'  => 'aiomatic_embeddings', 
                            'meta_query' => array(
                            array(
                                'key'     => 'quadrant_id', 
                                'value'   => $fv['id'], 
                                'compare' => '=', 
                            )
                            )
                        );
                        $posts = get_posts( $args );
                        if ( $posts ) 
                        {
                            $data .= empty($data) ? $posts[0]->post_content : "\n" . $posts[0]->post_content;
                            $found = true;
                            break;
                        }
                    }
                    if($found == true)
                    {
                        $result['data'] = $data;
                        $result['status'] = 'success';
                    }
                    else
                    {
                        $result['data'] = 'No results found';
                    }
                }
                else
                {
                    $result['data'] = 'No embeddings found';
                }
            }
            elseif(aiomatic_google_extension_is_google_embeddings_model($embeddings_model))
            {
                $error = '';
                $response = aiomatic_generate_embeddings_google($embeddings_model, $aiomatic_message, $error);
                if($response === false)
                {
                    $result['msg'] = 'Failed to call Embeddings API: ' . $error;
                    return $result;
                }
                if(isset($response['error']))
                {
                    $result['msg'] = 'Error while processing AI response: ' . $response['error'];
                    return $result;
                }
                $embedding = $response;
                $response = apply_filters( 'aiomatic_embeddings_reply_raw', $response, $aiomatic_message );
                apply_filters( 'aiomatic_ai_reply', $response, $query );
                if (!empty($embedding)) 
                {
                    require_once (dirname(__FILE__) . "/res/Qdrant.php");
                    $found_vectors = aiomatic_qdrant_query_vectors(trim($aiomatic_qdrant_api), $qdrant_url, $index_name, $pinecone_topk, $embedding );
                    $data = '';
                    $found = false;
                    foreach($found_vectors as $fv)
                    {
                        $args = array(
                            'post_type'  => 'aiomatic_embeddings', 
                            'meta_query' => array(
                            array(
                                'key'     => 'quadrant_id', 
                                'value'   => $fv['id'], 
                                'compare' => '=', 
                            )
                            )
                        );
                        $posts = get_posts( $args );
                        if ( $posts ) 
                        {
                            $data .= empty($data) ? $posts[0]->post_content : "\n" . $posts[0]->post_content;
                            $found = true;
                            break;
                        }
                    }
                    if($found == true)
                    {
                        $result['data'] = $data;
                        $result['status'] = 'success';
                    }
                    else
                    {
                        $result['data'] = 'No results found';
                    }
                }
                else
                {
                    $result['data'] = 'No embeddings found';
                }
            }
            else
            {
                if(aiomatic_is_aiomaticapi_key($token))
                {
                    $error = '';
                    $response = aiomatic_embeddings_aiomaticapi($token, $embeddings_model, $aiomatic_message, 0, $error);
                    if($response === false)
                    {
                        $result['data'] = 'Failed to call Embeddings API: ' . $error;
                        return $result;
                    }
                    if(isset($response->error))
                    {
                        $result['data'] = 'Error while processing AI response: ' . $response->error;
                        return $result;
                    }
                    if(!isset($response[0]->embedding))
                    {
                        $result['data'] = 'Failed to call Embeddings API: ' . print_r($response, true);
                        return $result;
                    }
                    $response = apply_filters( 'aiomatic_embeddings_reply_raw', $response, $aiomatic_message );
                    apply_filters( 'aiomatic_ai_reply', $response, $query );
                    $embedding = $response[0]->embedding;
                    if (!empty($embedding)) 
                    {
                        require_once (dirname(__FILE__) . "/res/Qdrant.php");
                        $found_vectors = aiomatic_qdrant_query_vectors(trim($aiomatic_qdrant_api), $qdrant_url, $index_name, $pinecone_topk, $embedding );
                        $data = '';
                        $found = false;
                        foreach($found_vectors as $fv)
                        {
                            $args = array(
                                'post_type'  => 'aiomatic_embeddings', 
                                'meta_query' => array(
                                array(
                                    'key'     => 'quadrant_id', 
                                    'value'   => $fv['id'], 
                                    'compare' => '=', 
                                )
                                )
                            );
                            $posts = get_posts( $args );
                            if ( $posts ) 
                            {
                                $data .= empty($data) ? $posts[0]->post_content : "\n" . $posts[0]->post_content;
                                $found = true;
                                break;
                            }
                        }
                        if($found == true)
                        {
                            $result['data'] = $data;
                            $result['status'] = 'success';
                        }
                        else
                        {
                            $result['data'] = 'No results found';
                        }
                    }
                    else
                    {
                        $result['data'] = 'No embeddings found';
                    }
                }
                elseif(aiomatic_check_if_azure($aiomatic_Main_Settings))
                {
                    $error = '';
                    $response = aiomatic_embeddings_azure($token, $embeddings_model, $aiomatic_message, 0, $error);
                    if($response === false)
                    {
                        $result['data'] = 'Failed to call Embeddings API: ' . $error;
                        return $result;
                    }
                    if(isset($response->error))
                    {
                        $result['data'] = 'Error while processing AI response: ' . $response->error;
                        return $result;
                    }
                    if(!isset($response[0]->embedding))
                    {
                        $result['data'] = 'Failed to call Embeddings API: ' . print_r($response, true);
                        return $result;
                    }
                    $response = apply_filters( 'aiomatic_embeddings_reply_raw', $response, $aiomatic_message );
                    apply_filters( 'aiomatic_ai_reply', $response, $query );
                    $embedding = $response[0]->embedding;
                    if (!empty($embedding)) {
                        require_once (dirname(__FILE__) . "/res/Qdrant.php");
                        $found_vectors = aiomatic_qdrant_query_vectors(trim($aiomatic_qdrant_api), $qdrant_url, $index_name, $pinecone_topk, $embedding );
                        $data = '';
                        $found = false;
                        foreach($found_vectors as $fv)
                        {
                            $args = array(
                                'post_type'  => 'aiomatic_embeddings', 
                                'meta_query' => array(
                                array(
                                    'key'     => 'quadrant_id', 
                                    'value'   => $fv['id'], 
                                    'compare' => '=', 
                                )
                                )
                            );
                            $posts = get_posts( $args );
                            if ( $posts ) 
                            {
                                $data .= empty($data) ? $posts[0]->post_content : "\n" . $posts[0]->post_content;
                                $found = true;
                                break;
                            }
                        }
                        if($found == true)
                        {
                            $result['data'] = $data;
                            $result['status'] = 'success';
                        }
                        else
                        {
                            $result['data'] = 'No results found';
                        }
                    }
                }
                else
                {
                    require_once (dirname(__FILE__) . "/res/openai/Url.php"); 
                    require_once (dirname(__FILE__) . "/res/openai/OpenAi.php");
                    $open_ai = new OpenAi($token);
                    if (isset($aiomatic_Main_Settings['openai_organization']) && $aiomatic_Main_Settings['openai_organization'] != '')
                    {
                        $open_ai->setORG($aiomatic_Main_Settings['openai_organization']);
                    }
                    $response = $open_ai->embeddings([
                        'input' => $aiomatic_message,
                        'model' => $embeddings_model
                    ]);
                    $response = json_decode($response, true);
                    if (isset($response['error']) && !empty($response['error'])) {
                        $result['data'] = $response['error']['message'];
                    } 
                    else 
                    {
                        $response = apply_filters( 'aiomatic_embeddings_reply_raw', (object)$response, $aiomatic_message );
                        $response = (array) $response;
                        apply_filters( 'aiomatic_ai_reply', $response, $query );
                        $embedding = $response['data'][0]['embedding'];
                        if (!empty($embedding)) {
                            require_once (dirname(__FILE__) . "/res/Qdrant.php");
                            $found_vectors = aiomatic_qdrant_query_vectors(trim($aiomatic_qdrant_api), $qdrant_url, $index_name, $pinecone_topk, $embedding );
                            $data = '';
                            $found = false;
                            foreach($found_vectors as $fv)
                            {
                                $args = array(
                                    'post_type'  => 'aiomatic_embeddings', 
                                    'meta_query' => array(
                                    array(
                                        'key'     => 'quadrant_id', 
                                        'value'   => $fv['id'], 
                                        'compare' => '=',
                                    )
                                    )
                                );
                                $posts = get_posts( $args );
                                if ( $posts ) 
                                {
                                    $data .= empty($data) ? $posts[0]->post_content : "\n" . $posts[0]->post_content;
                                    $found = true;
                                    break;
                                }
                            }
                            if($found == true)
                            {
                                $result['data'] = $data;
                                $result['status'] = 'success';
                            }
                            else
                            {
                                $result['data'] = 'No results found';
                            }
                        }
                    }
                }
            }
        }
    }
    else
    {
        $result['data'] = 'Unrecognized embeddings provider selected';
    }
    return $result;
}

function aiomatic_extract_keywords_internet($aicontent)
{
    $generated_text = '';
    $max_tokens = 2000;
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (!isset($aiomatic_Main_Settings['app_id']) || trim($aiomatic_Main_Settings['app_id']) == '') 
    {
        return $generated_text;
    }
    $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
    $appids = array_filter($appids);
    $token = $appids[array_rand($appids)];
    $token = apply_filters('aiomatic_openai_api_key', $token);
    $aicontent = trim(strip_shortcodes(strip_tags(str_replace('"', '\'', $aicontent))));
    if (isset($aiomatic_Main_Settings['keyword_extractor_prompt']) && trim($aiomatic_Main_Settings['keyword_extractor_prompt']) != '') 
    {
        $title_ai_command = trim($aiomatic_Main_Settings['keyword_extractor_prompt']);
        $title_ai_command = str_replace('%%original_prompt%%', $aicontent, $title_ai_command);
    }
    else
    {
        $title_ai_command = str_replace('%%original_prompt%%', $aicontent, '');
        $title_ai_command = 'Using which 2 keywords should I search the internet, so I get results related to the following text? Give me only the 2 search keywords, don\'t write anything else. Don\'t act as a virtual assistant, reply only with the keywords, as they will be used automatically for search. The text is: "' . $aicontent . '"?';
    }
    if(isset($aiomatic_Main_Settings['internet_model']) && $aiomatic_Main_Settings['internet_model'] != '')
    {
        $kw_model = $aiomatic_Main_Settings['internet_model'];
    }
    else
    {
        $kw_model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
    }
    if(isset($aiomatic_Main_Settings['internet_assistant_id']) && $aiomatic_Main_Settings['internet_assistant_id'] != '')
    {
        $internet_assistant_id = $aiomatic_Main_Settings['internet_assistant_id'];
    }
    else
    {
        $internet_assistant_id = '';
    }
    $max_tokens = aiomatic_get_max_tokens($kw_model);
    $query_token_count = count(aiomatic_encode($title_ai_command));
    $available_tokens = aiomatic_compute_available_tokens($kw_model, $max_tokens, $title_ai_command, $query_token_count);
    if($available_tokens <= AIOMATIC_MINIMUM_TOKENS_FOR_COMPLETIONS)
    {
        $string_len = strlen($title_ai_command);
        $string_len = $string_len / 2;
        $string_len = intval(0 - $string_len);
        $title_ai_command = aiomatic_substr($title_ai_command, 0, $string_len);
        $title_ai_command = trim($title_ai_command);
        $query_token_count = count(aiomatic_encode($title_ai_command));
        $available_tokens = $max_tokens - $query_token_count;
    }
    if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) 
    {
        $api_service = aiomatic_get_api_service($token, $kw_model);
        aiomatic_log_to_file('Calling ' . $api_service . ' (' . $kw_model . ') for internet access kws: ' . $title_ai_command);
    }
    $thread_id = '';
    $aierror = '';
    $finish_reason = '';
    $generated_text = aiomatic_generate_text($token, $kw_model, $title_ai_command, $available_tokens, 1, 1, 0, 0, false, 'shortcodeKeywordArticle', 0, $finish_reason, $aierror, true, false, false, '', '', 'user', $internet_assistant_id, $thread_id, '', 'disabled', '', false, false);
    if($generated_text === false)
    {
        aiomatic_log_to_file('Keywords generator error: ' . $aierror);
        return '';
    }
    else
    {
        $generated_text = trim(trim(trim(trim($generated_text), '.'), ' "\''));
    }
    if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) 
    {
        aiomatic_log_to_file('Successfully got API keyword result (for internet access):' . $generated_text);
    }
    return $generated_text;
}

function aiomatic_internet_result($query, $no_search_optimization = false, $locale = '')
{
    $query = trim(preg_replace('/\s\s+/', ' ', $query));
    $internet_search = array();
    if($no_search_optimization !== true)
    {
        $aikws = aiomatic_extract_keywords_internet($query);
        if(!empty($aikws))
        {
            $query = $aikws;
        }
    }
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['google_search_api']) && trim($aiomatic_Main_Settings['google_search_api']) != '') 
    {
        if (isset($aiomatic_Main_Settings['google_search_cx']) && trim($aiomatic_Main_Settings['google_search_cx']) != '') 
        {
            $max_count = 10;
            $is_ok = true;
            $page_number = 0;
            $result_number = 10;
            while($is_ok && count($internet_search) < $max_count)
            {
                if($page_number == 0)
                {
                    $first = 0;
                }
                else
                {
                    $first = ($page_number * $result_number) + 1;
                }
                if($first > 91)
                {
                    break;
                }
                $internet_params = array(
                    'q'   => urlencode( $query ),
                    'cx'  => trim($aiomatic_Main_Settings['google_search_cx']),
                    'key' => trim($aiomatic_Main_Settings['google_search_api']),
                    'num' => $result_number,
                    'start' => $first
                );
                if(!empty($locale))
                {
                    if(strstr($locale, 'country') !== false)
                    {
                        $internet_params['cr'] = $locale;
                    }
                    else
                    {
                        $internet_params['gl'] = $locale;
                    }
                }
                $feed_uri = add_query_arg( $internet_params, 'https://www.googleapis.com/customsearch/v1' );
                $responser = aiomatic_get_web_page($feed_uri);
                if ($responser === FALSE) 
                {
                    $is_ok = false;
                }
                else
                {
                    $json_resp = json_decode($responser);
                    if ($json_resp === null) 
                    {
                        $is_ok = false;
                    }
                    else
                    {
                        if (isset($json_resp->items)) 
                        {
                            foreach($json_resp->items as $jitem)
                            {
                                $internet_search[] = array('title' => $jitem->title, 'link' => $jitem->link, 'snippet' => $jitem->snippet);
                            }
                            $page_number++;
                        }
                        else
                        {
                            $is_ok = false;
                        }
                    }
                }
            }
        }
    }
    if (count($internet_search) == 0) 
    {
        if (isset($aiomatic_Main_Settings['bing_auth_internet']) && trim($aiomatic_Main_Settings['bing_auth_internet']) != '') 
        {
            $kkey = trim($aiomatic_Main_Settings['bing_auth_internet']);
            $curl = curl_init();
            $queryUrl = "https://api.bing.microsoft.com/v7.0/search?q=" . urlencode($query);
            if (!empty($locale)) 
            {
                $queryUrl .= '&cc=' . urlencode($locale);
            }
            curl_setopt_array($curl, [
                CURLOPT_URL => $queryUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => [
                    "Ocp-Apim-Subscription-Key: $kkey"
                ]
            ]);
            $html_data = curl_exec($curl);
            curl_close($curl);
            if ($html_data !== FALSE) 
            {
                $json = json_decode($html_data);
                if ($json !== null && isset($json->webPages->value)) {
                    foreach ($json->webPages->value as $jsx) {
                        if (isset($jsx->name)) {
                            if (!isset($jsx->snippet)) {
                                $jsx->snippet = '';
                            }
                            $internet_search[] = array('title' => $jsx->name, 'link' => $jsx->url, 'snippet' => $jsx->snippet);
                        }
                    }
                }
            }
        }
    }
    if(count($internet_search) == 0)
    {
        if (isset($aiomatic_Main_Settings['serper_auth']) && trim($aiomatic_Main_Settings['serper_auth']) != '')
        {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://google.serper.dev/search',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>'{"q":"' . str_replace('"', "'", $query) . '"}',
                CURLOPT_HTTPHEADER => array(
                    'X-API-KEY: ' . trim($aiomatic_Main_Settings['serper_auth']),
                    'Content-Type: application/json'
                ),
            ));
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $html_data = curl_exec($curl);
            if ($html_data !== FALSE) 
            {
                $json = json_decode($html_data);
                if ($json !== null) 
                {
                    if(isset($json->organic))
                    {
                        foreach($json->organic as $jsx)
                        {
                            if(isset($jsx->title))
                            {
                                if(!isset( $jsx->snippet))
                                {
                                    $jsx->snippet = '';
                                }
                                $internet_search[] = array('title' => $jsx->title, 'link' => $jsx->link, 'snippet' => $jsx->snippet);
                            }
                        }
                    }
                }
            }
        }
    }
    if(count($internet_search) == 0)
    {
        if (isset($aiomatic_Main_Settings['spaceserp_auth']) && trim($aiomatic_Main_Settings['spaceserp_auth']) != '')
        {
            $serpapi = 'https://api.spaceserp.com/google/search?q=' . urlencode($query) . '&apiKey=' . trim($aiomatic_Main_Settings['spaceserp_auth']);
            if(!empty($locale))
            {
                $serpapi .= '&gl=' . urlencode($locale) . '&hl=' . urlencode($locale);
            }
            $html_data = aiomatic_get_web_page($serpapi);
            if ($html_data !== FALSE) 
            {
                $json = json_decode($html_data);
                if ($json !== null) 
                {
                    if(isset($json->organic_results))
                    {
                        foreach($json->organic_results as $jsx)
                        {
                            if(isset($jsx->title))
                            {
                                if(!isset( $jsx->description))
                                {
                                    $jsx->description = '';
                                }
                                $internet_search[] = array('title' => $jsx->title, 'link' => $jsx->link, 'snippet' => $jsx->description);
                            }
                        }
                    }
                }
            }
        }
    }
    if(count($internet_search) == 0)
    {
        if (isset($aiomatic_Main_Settings['valueserp_auth']) && trim($aiomatic_Main_Settings['valueserp_auth']) != '')
        {
            $serpapi = 'https://api.valueserp.com/search?q=' . urlencode($query) . '&api_key=' . trim($aiomatic_Main_Settings['valueserp_auth']);
            if(!empty($locale))
            {
                if(strstr($locale, 'country') !== false)
                {
                    $serpapi .= '&cr=' . urlencode($locale);
                }
                else
                {
                    $serpapi .= '&gl=' . urlencode($locale);
                }
            }
            $html_data = aiomatic_get_web_page($serpapi);
            if ($html_data !== FALSE) 
            {
                $json = json_decode($html_data);
                if ($json !== null) 
                {
                    if(isset($json->organic_results))
                    {
                        foreach($json->organic_results as $jsx)
                        {
                            if(isset($jsx->title))
                            {
                                if(!isset( $jsx->snippet))
                                {
                                    $jsx->snippet = '';
                                }
                                $internet_search[] = array('title' => $jsx->title, 'link' => $jsx->link, 'snippet' => $jsx->snippet);
                            }
                        }
                    }
                }
            }
        }
    }
    if(count($internet_search) == 0)
    {
        if (isset($aiomatic_Main_Settings['serpapi_auth']) && trim($aiomatic_Main_Settings['serpapi_auth']) != '')
        {
            $serpapi = 'https://serpapi.com/search.json?q=' . urlencode($query) . '&api_key=' . trim($aiomatic_Main_Settings['serpapi_auth']);
            if(!empty($locale))
            {
                if(strstr($locale, 'country') !== false)
                {
                    $serpapi .= '&cr=' . urlencode($locale);
                }
                else
                {
                    $serpapi .= '&gl=' . urlencode($locale);
                }
            }
            $html_data = aiomatic_get_web_page($serpapi);
            if ($html_data !== FALSE) 
            {
                $json = json_decode($html_data);
                if ($json !== null) 
                {
                    if(isset($json->organic_results))
                    {
                        foreach($json->organic_results as $jsx)
                        {
                            if(isset($jsx->title))
                            {
                                if(!isset( $jsx->snippet))
                                {
                                    $jsx->snippet = '';
                                }
                                $internet_search[] = array('title' => $jsx->title, 'link' => $jsx->link, 'snippet' => $jsx->snippet);
                            }
                        }
                    }
                }
            }
        }
    }
    if (count($internet_search) == 0)
    {
        $query_arr = explode(',', $query);
        $query = $query_arr[0];
        require_once (dirname(__FILE__) . "/res/Bing.php");
        $bing = new AiomaticBing($query, true);
        if(isset($bing->data))
        {
            foreach($bing->data as $bg)
            {
                $internet_search[] = array('title' => $bg['title'], 'link' => $bg['link'], 'snippet' => $bg['description']);
            }
        }
        else
        {
            $burl = "https://www.bing.com/search?q=" . urlencode($query);
            if(!empty($locale))
            {
                if(strstr($locale, 'country') !== false)
                {
                    $burl .= '&cr=' . urlencode($locale);
                }
                else
                {
                    $burl .= '&gl=' . urlencode($locale);
                }
            }
            $html_data = aiomatic_get_web_page_from_search($burl, '');
            if ($html_data !== FALSE) 
            {
                preg_match_all('#<li class="b_algo">([\s\S]*?)<\/li>#i', $html_data, $htmlrez);
                if(isset($htmlrez[1][0]))
                {
                    preg_match_all('#<h2><a (?:target="_blank"\s)?href="([^"]*?)"[\s\S]*?>([\s\S]*?)<\/a><\/h2>[\s\S]*?b_algoSlug">([\s\S]*?)<\/span>#i', $htmlrez[1][0], $titlerez);
                    if(isset($titlerez[1][0]))
                    {
                        for($cnt = 0; $cnt < count($titlerez[1]); $cnt++)
                        {
                            $title = '';
                            $url = '';
                            $snippet = '';
                            if(isset($titlerez[1][$cnt]) && isset($titlerez[2][$cnt]) && isset($titlerez[3][$cnt]))
                            {
                                $url = $titlerez[1][$cnt];
                                $title = $titlerez[2][$cnt];
                                $snippet = $titlerez[3][$cnt];
                            }
                            if($title != '' && $url != '')
                            {
                                $internet_search[] = array('title' => strip_tags($title), 'link' => $url, 'snippet' => $snippet);
                            }
                        }
                    }
                }
            }
        }
    }
    if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) 
    {
        aiomatic_log_to_file('Internet results: ' . print_r($internet_search, true));
    }
    return $internet_search;
}

function aiomatic_get_web_page_from_search($url, $custom_cookie = '')
{
    $content = false;
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $user_agent = aiomatic_get_random_user_agent();
    require_once (dirname(__FILE__) . "/aiomatic-scraper.php"); 
    $html_cont = aiomatic_get_page_Puppeteer($url, $custom_cookie, $user_agent, '1', '', '', '', '', '');
    if($html_cont !== false)
    {
        return $html_cont;
    }
    if(function_exists('curl_version'))
    {
        $headers   = array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        );
        $ch = curl_init();
        if ($ch === FALSE) {
            aiomatic_log_to_file('curl not inited: ' . $url);
            $allowUrlFopen = preg_match('/1|yes|on|true/i', ini_get('allow_url_fopen'));
            if ($allowUrlFopen) {
                global $wp_filesystem;
            if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
                include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
                wp_filesystem($creds);
            }
            return $wp_filesystem->get_contents($url);
            }
        }
        if($custom_cookie != '')
        {
            $headers[] = 'Cookie: ' . $custom_cookie;
            curl_setopt($ch, CURLOPT_COOKIE , $custom_cookies);
        }
        $options    = array(
            CURLOPT_COOKIEJAR => get_temp_dir() . 'aiomaticcookie.txt',
            CURLOPT_COOKIEFILE => get_temp_dir() . 'aiomaticcookie.txt',
            CURLOPT_USERAGENT => $user_agent,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => true,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers
        );
        if($custom_cookie != '')
        {
            unset($options[CURLOPT_COOKIEJAR]);
            unset($options[CURLOPT_COOKIEFILE]);
        }
        if (isset($aiomatic_Main_Settings['proxy_url']) && $aiomatic_Main_Settings['proxy_url'] != '' && $aiomatic_Main_Settings['proxy_url'] != 'disable' && $aiomatic_Main_Settings['proxy_url'] != 'disabled') {
            $options[CURLOPT_PROXY] = $aiomatic_Main_Settings['proxy_url'];
            if (isset($aiomatic_Main_Settings['proxy_auth']) && $aiomatic_Main_Settings['proxy_auth'] != '') {
                $options[CURLOPT_PROXYUSERPWD] = $aiomatic_Main_Settings['proxy_auth'];
            }
        }
        curl_setopt_array($ch, $options);
        $content = curl_exec($ch);
        if($content === false)
        {
            aiomatic_log_to_file('Error occured in curl: ' . curl_error($ch) . ', url: ' . $url);
            $allowUrlFopen = preg_match('/1|yes|on|true/i', ini_get('allow_url_fopen'));
            if ($allowUrlFopen) {
                global $wp_filesystem;
            if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
                include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
                wp_filesystem($creds);
            }
            return $wp_filesystem->get_contents($url);
            }
        }
        curl_close($ch);
    }
    return $content;
}
add_action('upgrader_process_complete', 'aiomatic_updatePlugin', 10, 2);
function aiomatic_updatePlugin(\WP_Upgrader $upgrader, array $hook_extra)
{
    if (is_array($hook_extra) && array_key_exists('action', $hook_extra) && array_key_exists('type', $hook_extra) && array_key_exists('plugins', $hook_extra)) {
        if ($hook_extra['action'] == 'update' && $hook_extra['type'] == 'plugin' && is_array($hook_extra['plugins']) && !empty($hook_extra['plugins'])) {
            $this_plugin = plugin_basename(__FILE__);
            foreach ($hook_extra['plugins'] as $key => $plugin) {
                if ($this_plugin == $plugin) {
                    $this_plugin_updated = true;
                    break;
                }
            }
            unset($key, $plugin, $this_plugin);
            if (isset($this_plugin_updated) && $this_plugin_updated === true) {
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                global $wpdb;
                $charset_collate = $wpdb->get_charset_collate();
                aiomatic_register_aggregated_feed_table();
                $sql_create_table = "CREATE TABLE IF NOT EXISTS {$wpdb->aiomatict_shortcode_rez} (
                      post_id bigint(20) unsigned NOT NULL auto_increment,
                      post_hash text default '',
                      post_result text default '',
                      PRIMARY KEY  (post_id)
                 ) $charset_collate; ";
                dbDelta( $sql_create_table );
            }
        }
    }
}
add_action( 'rest_api_init', function () 
{
    register_rest_route( 'omniblock', 'v1/webhook', array(
      'methods' => ['GET', 'POST'],
      'callback' => 'aiomatic_custom_webhook_setup',
      'permission_callback' => '__return_true'
    ) );
});
function aiomatic_custom_webhook_setup() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $err = array();
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') 
    {
        if (isset($aiomatic_Main_Settings['omni_webhook']) && $aiomatic_Main_Settings['omni_webhook'] == 'on') 
        {
            if (isset($_REQUEST['omniblockid']) && !empty(trim($_REQUEST['omniblockid'])))
            {
                require_once (dirname(__FILE__) . "/aiomatic-automation.php"); 
                $cont = 0;
                $received_api_key = isset($_REQUEST['apikey']) ? $_REQUEST['apikey'] : '';
                $omniblockid = isset($_REQUEST['omniblockid']) ? $_REQUEST['omniblockid'] : '';
                $id_parts = explode('_', $omniblockid);
                if(!isset($id_parts[1]))
                {
                    $err['success'] = false;
                    $err['error'] = 'Incorrect OmniBlock ID submitted in request';
                    return $err;
                }
                if(count($id_parts) > 2)
                {
                    $err['success'] = false;
                    $err['error'] = 'Incorrect format for OmniBlock IDs';
                    return $err;
                }
                $param = $id_parts[0];
                $omniWebhookID = $id_parts[1];
                $rules = get_option('aiomatic_omni_list', array());
                $found = false;
                if (!empty($rules)) 
                {
                    $default_block_types = aiomatic_omniblocks_default_block_types(); 
                    foreach ($rules as $request => $bundle[]) 
                    {
                        if ($cont == $param) 
                        {
                            $bundle_values    = array_values($bundle);
                            $myValues         = $bundle_values[$cont];
                            $array_my_values  = array_values($myValues);for($iji=0;$iji<count($array_my_values);++$iji){if(is_string($array_my_values[$iji])){$array_my_values[$iji]=stripslashes($array_my_values[$iji]);}}
                            $schedule         = isset($array_my_values[0]) ? $array_my_values[0] : '';
                            $active           = isset($array_my_values[1]) ? $array_my_values[1] : '';
                            $last_run         = isset($array_my_values[2]) ? $array_my_values[2] : '';
                            $max              = isset($array_my_values[3]) ? $array_my_values[3] : '';
                            $main_keywords    = isset($array_my_values[4]) ? $array_my_values[4] : '';
                            $title_once       = isset($array_my_values[5]) ? $array_my_values[5] : '';
                            $rule_description = isset($array_my_values[6]) ? $array_my_values[6] : '';
                            $rule_unique_id   = isset($array_my_values[7]) ? $array_my_values[7] : '';
                            $sortable_cards   = isset($array_my_values[8]) ? $array_my_values[8] : '';
                            $more_keywords    = isset($array_my_values[9]) ? $array_my_values[9] : '';
                            $days_no_run      = isset($array_my_values[10]) ? $array_my_values[10] : '';
                            $block_data       = json_decode($sortable_cards, true);
                            if(!empty($block_data))
                            {
                                foreach($block_data as $index => $current_block)
                                {
                                    $card_type_found = array();
                                    foreach($default_block_types as $def_card)
                                    {
                                        if($current_block['type'] == $def_card['id'])
                                        {
                                            $card_type_found = $def_card;
                                            break;
                                        }
                                    }
                                    if(!empty($card_type_found) && $omniWebhookID == $current_block['identifier'] && $current_block['type'] === 'webhook_fire')
                                    {
                                        if(isset($current_block['parameters']['api_key']) && !empty(trim($current_block['parameters']['api_key'])))
                                        {
                                            $api_key = $current_block['parameters']['api_key'];
                                            if(!isset($_REQUEST['apikey']))
                                            {
                                                $err['success'] = false;
                                                $err['error'] = 'You need to specify an API key for this request';
                                                return $err;
                                            }
                                            if($_REQUEST['apikey'] != $api_key)
                                            {
                                                $err['success'] = false;
                                                $err['error'] = 'Invalid API key provided';
                                                return $err;
                                            }
                                        }
                                        $response = json_encode(array('success' => true, 'data' => array('rule_id' => $param, 'omniblock_id' => $omniWebhookID)));
                                        if (!headers_sent()) {
                                            header('Content-Type: application/json; charset=utf-8');
                                            header('Content-Length: ' . strlen($response));
                                            header('Connection: close');
                                            header('Cache-Control: no-cache, must-revalidate');
                                            header('X-Accel-Buffering: no');
                                        }
                                        if (session_id()) {
                                            session_write_close();
                                        }
                                        while (ob_get_level() > 0) 
                                        {
                                            ob_end_clean();
                                        }
                                        if (function_exists('apache_setenv')) {
                                            apache_setenv('no-gzip', 1);
                                        }
                                        ini_set('zlib.output_compression', 0);
                                        echo $response;
                                        if (ob_get_level() > 0) 
                                        {
                                            ob_flush();
                                        }
                                        flush();
                                        if (function_exists('fastcgi_finish_request')) 
                                        {
                                            fastcgi_finish_request();
                                        }
                                        $return_me = aiomatic_run_rule($cont, 5, 1, 0, null, '', $omniWebhookID);
                                        if($return_me == 'fail')
                                        {
                                            aiomatic_log_to_file('OmniBlock Webhook rule running failed, rule ID: ' . $param . ' OmniBlock ID: ' . $omniWebhookID);
                                        }
                                        wp_die();
                                    }
                                }
                            }
                        }
                        $cont = $cont + 1;
                    }
                    if($found == false)
                    {
                        $err['success'] = false;
                        $err['error'] = 'Specified OmniBlock ID not found';
                        return $err;
                    }
                } 
                else 
                {
                    $err['success'] = false;
                    $err['error'] = 'No rules found for aiomatic_omni_list!';
                    return $err;
                }
            }
            else 
            {
                $err['success'] = false;
                $err['error'] = 'OmniBlock ID not specified';
                return $err;
            }
        } 
        else 
        {
            $err['success'] = false;
            $err['error'] = 'Webhooks API not enabled';
            return $err;
        }
    }
    else 
    {
        $err['success'] = false;
        $err['error'] = 'Aiomatic not enabled';
        return $err;
    }
}

register_activation_hook(__FILE__, 'aiomatic_activation_callback');
function aiomatic_activation_callback($defaults = FALSE)
{
    if (!get_option('aiomatic_posts_per_page') || $defaults === TRUE) {
        if ($defaults === FALSE) {
            add_option('aiomatic_posts_per_page', '12', '', false);
        } else {
            aiomatic_update_option('aiomatic_posts_per_page', '12', false);
        }
    }
    if (!get_option('aiomatic_Main_Settings') || $defaults === TRUE) {
        $aiomatic_Main_Settings = array(
            'aiomatic_enabled' => 'on',
            'translate' => 'disabled',
            'translate_source'  => 'disabled',
            'second_translate' => 'disabled',
            'bing_region' => '',
            'video_cfg_scale' => '',
            'cfg_seed' => '',
            'motion_bucket_id' => '',
            'custom_html2' => '',
            'custom_html' => '',
            'embedding_template' => '',
            'comment_embedding_template' => '',
            'bulk_embedding_template' => '',
            'google_trans_auth' => '',
            'deppl_free' => '',
            'deepl_auth' => '',
            'serpapi_auth' => '',
            'bing_auth' => '',
            'bing_auth_internet' => '',
            'valueserp_auth' => '',
            'spaceserp_auth' => '',
            'serper_auth' => '',
            'google_search_api' => '',
            'google_search_cx' => '',
            'yt_app_id' => '',
            'copy_locally' => 'on',
            'url_image' => '',
            'drive_directory' => 'MyImages',
            'bucket_name' => '',
            'bucket_region' => '',
            'wasabi_region' => '',
            's3_user' => '',
            's3_pass' => '',
            'wasabi_directory' => '',
            'wasabi_bucket' => '',
            'wasabi_region' => '',
            'wasabi_pass' => '',
            'wasabi_user' => '',
            'cloud_directory' => '',
            'cloud_account' => '',
            'cloud_bucket' => '',
            'cloud_pass' => '',
            'cloud_user' => '',
            'digital_directory' => '',
            'digital_endpoint' => '',
            'digital_pass' => '',
            'digital_user' => '',
            'no_img_translate' => '',
            'omni_webhook' => '',
            'omni_caching' => '',
            'dalle_style' => 'vivid',
            'midjourney_image_model' => 'fast',
            'midjourney_image_engine' => 'midjourney',
            'replicate_image_model' => 'ac732df83cea7fff18b8472768c88ad041fa750ff7682a21affe81863cbe77e4',
            'ai_resize_width' => '',
            'disable_compress' => '',
            'compress_quality' => '75',
            'ai_resize_height' => '',
            'ai_resize_quality' => '',
            'request_delay' => '',
            'player_height' => '',
            'player_width' => '',
            'improve_yt_kw' => '',
            'yt_kw_model' => 'gpt-4o-mini',
            'ai_writer_model' => 'gpt-4o-mini',
            'writer_assistant_id' => '',
            'kw_assistant_id' => '',
            'yt_assistant_id' => '',
            'ai_writer_title_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Create a captivating and concise SEO title in English for your WordPress %%post_type%%: "%%post_title_idea%%". Boost its search engine visibility with relevant keywords for maximum impact.',
            'ai_writer_seo_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Craft an enticing and succinct meta description in English for your WordPress %%post_type%%: "%%post_title_idea%%". Emphasize the notable features and advantages in just 155 characters, incorporating relevant keywords to optimize its SEO performance.',
            'ai_writer_content_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Create a captivating and comprehensive English description for your WordPress %%post_type%%: "%%post_title_idea%%". Dive into specific details, highlighting its unique features of this subject, if possible, benefits, and the value it brings. Craft a compelling narrative around the %%post_type%% that captivates the audience. Use HTML for formatting, include unnumbered lists and bold. Writing Style: Creative. Tone: Neutral.',
            'ai_writer_excerpt_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Write a captivating and succinct English summary for the WordPress %%post_type%%: "%%post_title_idea%%", accentuating its pivotal features, advantages, and distinctive qualities.',
            'ai_writer_tags_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Suggest a series of pertinent keywords in English for your WordPress %%post_type%%: "%%post_title_idea%%". These keywords should be closely connected to the %%post_type%%, optimizing its visibility. Please present the keywords in a comma-separated format without using symbols like -, #, etc.',
            'yt_kw_prompt' => 'Using which 2 keywords should I search YouTube, to get the most relevant videos for this text? Provide a single variant, write only the 2 keywords, nothing else. Don\'t act as a virtual assistant, reply only with the keywords, as they will be used automatically for search. The text is: "%%aiomatic_query%%"',
            'sentence_list' => 'This is one %adjective %noun %sentence_ending
This is another %adjective %noun %sentence_ending
I %love_it %nouns , because they are %adjective %sentence_ending
My %family says this plugin is %adjective %sentence_ending
These %nouns are %adjective %sentence_ending',
            'sentence_list2' => 'Meet this %adjective %noun %sentence_ending
This is the %adjective %noun ever %sentence_ending
I %love_it %nouns , because they are the %adjective %sentence_ending
My %family says this plugin is very %adjective %sentence_ending
These %nouns are quite %adjective %sentence_ending',
            'variable_list' => 'adjective_very => %adjective;very %adjective;

adjective => clever;interesting;smart;huge;astonishing;unbelievable;nice;adorable;beautiful;elegant;fancy;glamorous;magnificent;helpful;awesome

noun_with_adjective => %noun;%adjective %noun

noun => plugin;WordPress plugin;item;ingredient;component;constituent;module;add-on;plug-in;addon;extension

nouns => plugins;WordPress plugins;items;ingredients;components;constituents;modules;add-ons;plug-ins;addons;extensions

love_it => love;adore;like;be mad for;be wild about;be nuts about;be crazy about

family => %adjective %family_members;%family_members

family_members => grandpa;brother;sister;mom;dad;grandma

sentence_ending => .;!;!!',
            'auto_clear_logs' => 'No',
            'run_after' => '',
            'max_len' => '',
            'ai_image_size' => '512x512',
            'ai_image_model' => 'dalle2',
            'back_color' => '#ffffff',
            'form_placeholder' => 'AI Result',
            'show_advanced' => '',
            'store_data_forms' => 'off',
            'default_ai_model' => 'gpt-4o-mini',
            'show_rich_editor' => '',
            'enable_copy' => '',
            'enable_download' => '',
            'enable_char_count' => '',
            'submit_location' => '1',
            'submit_align' => '1',
            'text_color' => '#000000',
            'but_color' => '#424242',
            'btext_color' => '#ffffff',
            'min_len' => '',
            'kw_lang' => 'en_US',
            'kw_method' => 'builtin',
            'pinecone_index' => '',
            'pinecone_namespace' => '',
            'qdrant_index' => '',
            'qdrant_name' => '',
            'pinecone_topk' => '1',
            'embeddings_model' => AIOMATIC_DEFAULT_MODEL_EMBEDDING,
            'run_before' => '',
            'enable_logging' => 'on',
            'app_id' => '',
            'stability_app_id' => '',
            'midjourney_app_id' => '',
            'replicate_app_id' => '',
            'headlessbrowserapi_key' => '',
            'phantom_path' => '',
            'phantom_timeout' => '',
            'multi_separator' => '',
            'azure_endpoint' => '',
            'azure_api_selector_embeddings' => '',
            'azure_api_selector_dalle2' => '',
            'azure_api_selector_dalle3' => '',
            'azure_api_selector_assistants' => '',
            'azure_api_selector' => '',
            'app_id_claude' => '',
            'app_id_groq' => '',
            'app_id_nvidia' => '',
            'app_id_xai' => '',
            'openai_organization' => '',
            'app_id_google' => '',
            'app_id_openrouter' => '',
            'app_id_huggingface' => '',
            'ollama_url' => '',
            'app_id_perplexity' => '',
            'multiple_key' => '',
            'api_selector' => 'openai',
            'pinecone_app_id' => '',
            'embeddings_api' => 'pinecone',
            'qdrant_app_id' => '',
            'elevenlabs_app_id' => '',
            'google_app_id' => '',
            'did_app_id' => '',
            'azure_speech_id' => '',
            'steps' => '50',
            'cfg_scale' => '7',
            'clip_guidance_preset' => 'NONE',
            'clip_style_preset' => 'NONE',
            'stable_model' => AIOMATIC_STABLE_DEFAULT_MODE,
            'prompt_strength' => '0.8',
            'num_inference_steps' => '4',
            'ai_scheduler' => 'DPMSolverMultistep',
            'replicate_ratio' => '',
            'custom_params_replicate' => '',
            'sampler' => 'auto',
            'enable_detailed_logging' => '',
            'rule_timeout' => '36000',
            'kws_case' => '',
            'no_new_tab_kw' => '',
            'kw_skip_ids' => '',
            'partial_kws' => '',
            'email_address' => '',
            'send_email' => '',
            'best_password' => '',
            'best_user' => '',
            'improve_keywords' => 'openai',
            'image_pool' => '4',
            'random_image_sources' => '',
            'random_results_order' => '',
            'image_query_translate_en' => '',
            'kw_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Extract a comma-separated list of the most relevant keywords from the text, prioritizing specific references over general keywords. Add the highest priority to the most specific keyword that is still related to the main topic. The text is: %%content%%.',
            'kw_model' => 'gpt-4o-mini',
            'keyword_model' => 'gpt-4o-mini',
            'internet_model' => 'gpt-4o-mini',
            'assistant_model' => 'gpt-4o-mini',
            'aicontent_model' => 'gpt-4o-mini',
            'comment_model' => 'gpt-4o-mini',
            'tax_description_model' => 'gpt-4o-mini',
            'keyword_assistant_id' => '',
            'tax_assistant_id' => '',
            'aicontent_assistant_id' => '',
            'internet_assistant_id' => '',
            'wizard_assistant_id' => '',
            'comment_assistant_id' => '',
            'enable_wpcli' => '',
            'rest_api_init' => '',
            'rest_api_keys' => '',
            'comment_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Write a reply for %%username%%\'s comment on the post titled "%%post_title%%". The user\'s comment is: %%comment%%',
            'tax_description_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Craft an SEO-optimized description for a WordPress %%term_taxonomy_name%% titled "%%term_name%%." Ensure that the description not only provides an informative summary but also incorporates relevant keywords and phrases to enhance search engine visibility.',
            'aicontent_temperature' => '1',
            'aicontent_top_p' => '1',
            'aicontent_presence_penalty' => '0',
            'aicontent_frequency_penalty' => '0',
            'tax_description_auto' => array(),
            'max_tax_nr' => '1',
            'tax_description_manual' => array(),
            'tax_seo_auto' => 'off',
            'overwite_tax' => '',
            'tax_seo_description_model' => 'gpt-4o-mini',
            'tax_seo_assistant_id' => '',
            'tax_seo_description_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Generate a concise, SEO-friendly description (maximum 50 words) for a WordPress %%term_taxonomy_name%% titled "%%term_name%%." Ensure the description effectively summarizes the term while incorporating relevant keywords to enhance search engine visibility.',
            'keyword_prompts' => 'Do not act as a virtual assistant, ask only what you are asked. I need to find highly relevant royalty-free images for an article heading, please extract a comma-separated list of the most relevant keywords or key phrases from the heading, prioritizing specific references over general keywords. Add the highest priority to the most specific keyword that is still related to the main topic. Keep in mind also the main subject of the post title when you suggest the keywords. I need the most relevant images, based on the keywords you return. Remember, also include the general niche keyword in the key phrase, to allow images to be relevant to the current subject. For example, if the heading is about food and the article is about dogs, don\'t just return food, but instead, return \'dog food\'. By doing so, you can help me find more appropriate and targeted images for the article heading. The blog post heading title is: "%%post_title%%". Post title is: "%%original_post_title%%"',
            'spin_lang' => 'English',
            'exclude_words' => '',
            'spin_text' => 'disabled',
            'spin_what' => 'all',
            'best_humanize' => '',
            'no_title' => '',
            'no_html_check' => 'on',
            'protect_html' => 'on',
            'swear_filter' => '',
            'no_undetectibility' => '',
            'no_media_library' => '',
            'no_post_editor' => '',
            'no_elementor' => '',
            'clear_omni' => '',
            'no_pre_code_remove' => '',
            'no_omni_shortcode_render' => '',
            'ai_seed' => '',
            'store_data' => 'off',
            'store_data_rules' => 'off',
            'apiKey' => '',
            'resize_height' => '',
            'resize_width' => '',
            'resize_quality' => '',
            'morguefile_api' => '',
            'morguefile_secret' => '',
            'pexels_api' => '',
            'flickr_api' => '',
            'flickr_license' => '',
            'flickr_order' => '',
            'pixabay_api' => '',
            'imgtype' => '',
            'img_order' => '',
            'img_cat' => '',
            'img_width' => '',
            'img_mwidth' => '',
            'img_ss' => '',
            'img_editor' => '',
            'img_language' => '',
            'unsplash_key' => '',
            'google_images' => 'on',
            'google_images_api' => '',
            'pixabay_scrape' => 'on',
            'scrapeimgtype' => '',
            'scrapeimg_orientation' => '',
            'scrapeimg_order' => '',
            'scrapeimg_cat' => '',
            'scrapeimg_width' => '',
            'scrapeimg_height' => '',
            'attr_text' => '<br/><br/>Images by <a href="%%image_source_url%%">%%image_source_name%%</a>. Free for commercial use, no attribution required.',
            'textrazor_key' => '',
            'neuron_key' => '',
            'neuron_project' => '',
            'amazon_app_secret' => '',
            'amazon_app_id' => '',
            'bimage' => '',
            'plagiarism_api' => '',
            'no_royalty_skip' => '',
            'proxy_url' => '',
            'proxy_auth' => '',
            'proxy_ai' => '',
            'do_not_check_duplicates' => '',
            'no_random_titles' => '',
            'draft_first' => '',
            'global_req_words' => '',
            'require_only_one' => '',
            'global_ban_words' => '',
            'email_notification' => '',
            'image_ai_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Choose the most relevant image URL, based on its file name, for the post titled "%%post_title%%." If no matching image is found, select a random one from the list. Respond solely with the URL of the chosen image. The image URL list is: %%image_list%%',
            'image_ai_model' => 'gpt-4o-mini',
            'img_assistant_id' => '',
            'use_image_ai' => '',
            'gpt4_context_limit' => '',
            'gpt35_context_limit' => '',
            'claude_context_limit_200k' => '',
            'assist_max_completion_token' => '',
            'assist_max_prompt_token' => '',
            'claude_context_limit' => '',
            'embeddings_related' => '',
            'embeddings_forms' => '',
            'embeddings_omni' => '',
            'embeddings_assistant' => '',
            'embeddings_edit_short' => '',
            'embeddings_article_short' => '',
            'embeddings_chat_short' => '',
            'embeddings_edit' => '',
            'embeddings_bulk' => '',
            'embeddings_bulk_title' => '',
            'embeddings_bulk_sections' => '',
            'embeddings_bulk_intro' => '',
            'embeddings_bulk_content' => '',
            'embeddings_bulk_qa' => '',
            'embeddings_bulk_outro' => '',
            'embeddings_bulk_excerpt' => '',
            'embeddings_single' => '',
            'embeddings_related_namespace' => '',
            'embeddings_forms_namespace' => '',
            'embeddings_omni_namespace' => '',
            'embeddings_assistant_namespace' => '',
            'embeddings_edit_short_namespace' => '',
            'embeddings_article_short_namespace' => '',
            'embeddings_chat_short_namespace' => '',
            'embeddings_edit_namespace' => '',
            'embeddings_bulk_namespace' => '',
            'embeddings_bulk_title_namespace' => '',
            'embeddings_bulk_sections_namespace' => '',
            'embeddings_bulk_intro_namespace' => '',
            'embeddings_bulk_content_namespace' => '',
            'embeddings_bulk_qa_namespace' => '',
            'embeddings_bulk_outro_namespace' => '',
            'embeddings_bulk_excerpt_namespace' => '',
            'embeddings_single_namespace' => '',
            'internet_related' => '',
            'internet_edit_short' => '',
            'internet_article_short' => '',
            'internet_chat_short' => '',
            'internet_edit' => '',
            'internet_bulk' => '',
            'internet_bulk_title' => '',
            'internet_bulk_sections' => '',
            'internet_bulk_intro' => '',
            'internet_bulk_content' => '',
            'internet_bulk_qa' => '',
            'internet_bulk_outro' => '',
            'internet_bulk_excerpt' => '',
            'internet_forms' => '',
            'internet_omni' => '',
            'internet_assistant' => '',
            'results_num' => '3',
            'auto_namspace' => '',
            'comment_auto_namspace' => '',
            'bulk_namspace' => '',
            'index_types' => array(),
            'comment_index_types' => array(),
            'rewrite_embedding' => '',
            'embedding_rw_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Rewrite the given content concisely, preserving its style and information, while ensuring the rewritten text stays within 300 words. Each paragraph should range between 60 to 120 words. Exclude non-textual elements and unnecessary repetition. Conclude with a statement directing readers to find more information at %%post_url%%. If these guidelines cannot be met, send an empty response. The content is as follows: %%post_content%%',
            'embedding_rw_model' => 'gpt-4o-mini',
            'emb_assistant_id' => '',
            'internet_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Web search results:
%%web_results%%
Current date: %%current_date%%
Instructions: Using the provided web search results, write a comprehensive reply to the given query. Make sure to cite results using <a href="(URL)">[[number]]</a> notation after the reference. If the provided search results refer to multiple subjects with the same name, write separate answers for each subject.
Query: %%original_query%%',
            'internet_single_template' => '[%%result_counter%%]: %%result_title%% %%result_snippet%%
URL: %%result_link%%',
            'internet_single' => '',
            'internet_gl' => '',
            'keyword_extractor_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Using which 2 keywords should I search the internet, so I get results related to the following text? Give me only the 2 search keywords, don\'t write anything else. Don\'t act as a virtual assistant, reply only with the keywords, as they will be used automatically for search. The text is: "%%original_prompt%%"?',
            'alternate_continue' => '',
            'whole_prompt' => 'on',
            'external_products' => '',
            'continue_prepend' => 'Continue the article from below, add new content to it. Be sure to keep the same writing style,  Content should be ready to post, no editing required. Language: English, friendly tone, professional and rich with information related to post title. Add headings in <h2> format. Do not add texts like "Article written by...". Do not add heading for Introduction. Do not add article title in content. Do not add images in content. Don\'t add a conclusion, nor a summary to the end of the article.',
            'continue_append' => '',
            'markdown_parse' => 'on',
            'first_embeddings' => '',
            'nlbr_parse' => '',
            'no_max' => '',
            'no_jobs' => '',
            'not_important' => '',
            'bing_off' => '',
            'ai_off' => '',
            'pre_code_off' => '',
            'max_retry' => '5',
            'max_chat_retry' => '',
            'max_timeout' => '',
            'rel_search' => array('post_title', 'post_content'),
            'ignored_users' => 'admin',
            'enable_tracking' => '',
            'assistant_placement' => 'below',
            'assistant_disable' => '',
            'assistant_not_logged' => 'disable',
            'assistant_image_size' => '512x512',
            'assistant_temperature' => '1',
            'assistant_top_p' => '1',
            'assistant_ppenalty' => '0',
            'assistant_fpenalty' => '0',
            'no_content' => '',
            'tag_name' => '',
            'post_id' => '',
            'post_name' => '',
            'page_id' => '',
            'post_parent' => '',
            'post_status' => '',
            'type_post' => 'post',
            'pagename' => '',
            'search_offset' => '',
            'search_query' => '',
            'meta_name' => '',
            'meta_value' => '',
            'year' => '',
            'month' => '',
            'day' => '',
            'order' => '',
            'orderby' => '',
            'featured_image' => 'any',
            'max_posts' => '',
            'category_name' => '',
            'author_id' => '',
            'author_name' => '',
            'no_twice' => 'on',
            'custom_name' => 'aiomatic_published',
            'secret_word' => '',
            'auto_edit' => 'disabled'
        );
        if ($defaults === FALSE) {
            add_option('aiomatic_Main_Settings', $aiomatic_Main_Settings, '', false);
        } else {
            aiomatic_update_option('aiomatic_Main_Settings', $aiomatic_Main_Settings, false);
        }
    }
    if (!get_option('aiomatic_Spinner_Settings') || $defaults === TRUE) {
        $aiomatic_Spinner_Settings = array(
            'aiomatic_spinning' => '',
            'run_background' => '',
            'enable_default' => '',
            'post_posts' => '',
            'post_pages'  => 'on',
            'post_custom' => 'on',
            'except_type' => '',
            'only_type' => '',
            'disabled_categories' => array(),
            'disable_tags' => '',
            'disable_users' => '',
            'change_status' => 'no',
            'store_data' => 'off',
            'delay_post' => '',
            'process_event' => 'publish',
            'use_template_manual' => '',
            'use_template_auto' => '',
            'append_spintax' => 'disabled',
            'append_location' => 'content',
            'url_image_list' => '',
            'ai_featured_image_edit' => 'disabled',
            'ai_featured_image_edit_content' => 'disabled',
            'ai_featured_image_engine' => '2',
            'ai_featured_image_engine_content' => '2',
            'ai_image_command_edit_content' => 'Slightly change the image, making it unique.',
            'ai_image_command_edit' => 'Slightly change the image, making it unique.',
            'image_strength' => '0.90',
            'image_strength_content' => '0.90',
            'max_edit_content' => '',
            'append_toc' => 'disabled',
            'when_toc' => '4',
            'max_nr' => '1',
            'delay_request' => '',
            'title_toc' => 'Table of Contents',
            'allow_hide_toc' => 'on',
            'hierarchy_toc' => 'on',
            'add_numbers_toc' => '',
            'float_toc' => 'none',
            'color_toc' => 'gray',
            'heading_levels1' => 'on',
            'heading_levels2' => 'on',
            'heading_levels3' => 'on',
            'heading_levels4' => 'on',
            'heading_levels5' => 'on',
            'heading_levels6' => 'on',
            'exclude_toc' => '',
            'ai_rewriter' => 'disabled',
            'ai_instruction' => 'Rewrite an HTML article to be 100% unique while keeping its high quality and original meaning. Key instructions: Thoroughly paraphrase, including altering sentence structures and using synonyms; reorganize paragraphs and points for a new perspective; add relevant information like current statistics or examples; replace examples/analogies with new, equivalent ones; create new headings and subheadings that reflect the restructured content; adjust the tone to differ from the original, ensuring coherence and logical structure, and retain any specific SEO keywords. Act as a Content Writer, not as a Virtual Assistant. Return only the content requested, without any additional comments or text. The content provided will be automatically published on my website. The article is below:\n\n',
            'ai_instruction_title' => 'Rewrite the article title from below to make it more engaging: ',
            'ai_instruction_slug' => 'Rewrite this WordPress post slug, improve it for better SEO, but keep it short: ',
            'no_slug' => 'on',
            'edit_temperature' => '',
            'ai_vision_add' => '',
            'preppend_add' => '',
            'append_add' => '',
            'ai_vision_cat' => '',
            'ai_vision_com' => '',
            'no_approve' => '',
            'ai_vision_seo' => '',
            'ai_vision_tag' => '',
            'add_custom' => 'disabled',
            'ai_custom_field' => '',
            'no_custom_field_prompt' => '',
            'no_custom_tax_prompt' => '',
            'ai_custom_tax' => '',
            'max_custom' => '',
            'skip_inexist_custom' => '',
            'custom_assistant_id' => '',
            'custom_model' => 'gpt-4o-mini',
            'ai_vision_custom' => '',
            'ai_vision' => '',
            'edit_top_p' => '',
            'edit_presence_penalty' => '',
            'edit_frequency_penalty' => '',
            'max_char_chunks' => '',
            'max_char' => '',
            'no_title' => 'on',
            'rewrite_url' => '',
            'edit_model' => 'gpt-4o-mini',
            'edit_assistant_id' => '',
            'append_assistant_id' => '',
            'no_content' => 'yes',
            'no_excerpt' => 'on',
            'max_slug_len' => '',
            'ai_instruction_excerpt' => 'Rewrite the article excerpt from below to make it more engaging while keeping the HTML tags unchanged. Edit only the visible content that is rendered in the HTML and displayed on the front end: ',
            'ai_featured_image' => 'disabled',
            'ai_featured_image_source' => '1',
            'ai_image_command' => 'Generate a high-resolution, visually compelling image that creatively interprets the theme encapsulated by this post title: "%%post_title%%". The image should be versatile enough to fit various niches, from technology and lifestyle to nature and science. It should feature a central, eye-catching element that abstractly represents the topic, surrounded by relevant, subtler motifs that provide context and depth. The composition should be balanced and aesthetically pleasing, with a harmonious color palette that complements the mood of the title. The artwork should be suitable for use as a captivating header image for a blog post.',
            'image_size' => '',
            'min_char' => '',
            'images' => '',
            'videos' => '',
            'link_juicer_prompt' => 'Don\'t act as a virtual assistant, return only the content you are asked. Generate a comma-separated list of relevant keywords for the post title (for use in the Link Juicer plugin): "%%post_title%%".',
            'link_juicer_assistant_id' => '',
            'link_juicer_model' => 'gpt-4o-mini',
            'ai_vision_link_juicer' => '',
            'add_links' => 'disabled',
            'link_method' => 'aiomatic',
            'max_links' => '3-5',
            'link_type' => 'internal',
            'link_list' => '',
            'link_post_types' => 'post',
            'link_nofollow' => '',
            'add_cats' => 'disabled',
            'max_cats' => '',
            'skip_inexist' => '',
            'ai_cats' => 'Generate a comma-separated list of relevant categories for the post title: "%%post_title%%". These categories must accurately categorize the article within the broader topics or themes of your blog, aiding in the organization and navigation of your content.',
            'cats_model' => 'gpt-4o-mini',
            'add_tags' => 'disabled',
            'max_tags' => '',
            'skip_inexist_tags' => '',
            'ai_tags' => 'Generate a comma-separated list of relevant tags for the post title: "%%post_title%%". These tags must accurately reflect the key topics, themes, or keywords associated with the article and help improve its discoverability and organization.',
            'tags_model' => 'gpt-4o-mini',
            'headings' => '',
            'enable_ai_images' => '',
            'headings_ai_command' => 'Write %%needed_heading_count%% relevant PAA (People Also Asked) related questions, each on a new line, for the title: %%post_title%%',
            'headings_model' => 'gpt-4o-mini',
            'headings_assistant_id' => '',
            'meta_assistant_id' => '',
            'categories_assistant_id' => '',
            'tags_assistant_id' => '',
            'comments_assistant_id' => '',
            'ai_command' => 'Write a comprehensive and SEO-optimized article on the topic of "%%post_title%%". Incorporate relevant keywords naturally throughout the article to enhance search engine visibility. This article must provide valuable information to readers and be well-structured with proper headings, bullet points, and HTML formatting. If needed, you can use WordPress related CSS styling for the article. When applicable, add also HTML tables with WordPress styling (you can use WordPress table classes). If added, table data must be relevant, creative, short and simple. 
            Don\'t add an introductory or a conclusion section to the article. You can add also some other sections, when they fit the article\'s subject, like: benefits and practical tips, case studies, first had experience.
            Please ensure that the article is at least 1200 words in length and adheres to best SEO practices, including proper header tags (H1, H2, H3), meta title, and meta description.
            Feel free to use a friendly, conversational tone and make the article as informative and engaging as possible while ensuring it remains factually accurate and well-researched. Act as a Content Writer, not as a Virtual Assistant. Return only the content requested, without any additional comments or text. The content provided will be automatically published on my website.',
            'max_seed_tokens' => '',
            'max_result_tokens' => '',
            'max_continue_tokens' => '',
            'max_tokens' => '2048',
            'temperature' => '1',
            'top_p' => '1',
            'presence_penalty' => '0',
            'frequency_penalty' => '0',
            'store_data' => '',
            'model' => 'gpt-4o-mini',
            'ai_comments' => 'Write a single comment (don\'t start a new line) for the post title: %%post_title%%
Previous comments are:
%%previous_comments%%
%%comment_author_name%%:',
            'star_count' => '5',
            'prev_comms' => '5',
            'max_comments' => '1-2',
            'add_comments' => 'disabled',
            'comments_model' => 'gpt-4o-mini',
            'user_list' => '%%random_user%%',
            'url_list' => '',
            'max_time' => '',
            'min_time' => '',
            'seo_model' => 'gpt-4o-mini',
            'seo_max_char' => '',
            'seo_copy_excerpt' => '',
            'content_text_speech' => 'off',
            'did_image' => 'https://create-images-results.d-id.com/api_docs/assets/noelle.jpeg',
            'audio_template' => '%%post_content%%',
            'did_voice' => 'microsoft:en-US-JennyNeural:Cheerful',
            'eleven_voice' => '',
            'ai_seo' => 'Craft an SEO meta description that optimizes the visibility and click-through rate for the post titled "%%post_title%%." The meta description should be concise, engaging, and provide a clear and compelling summary of the article\'s content, while also incorporating relevant keywords.',
            'add_seo' => 'disabled',
            'eleven_model_id' => '',
            'eleven_voice_custom' => '',
            'voice_stability' => '',
            'voice_similarity_boost' => '',
            'voice_style' => '',
            'speaker_boost' => '',
            'open_model_id' => 'tts-1',
            'open_voice' => 'alloy',
            'open_format' => 'mp3',
            'open_speed' => '1',
            'voice_language' => 'en-US',
            'google_voice' => '',
            'audio_profile' => '',
            'voice_speed' => '1',
            'voice_pitch' => '0',
            'text_to_audio' => '%%post_content%%',
            'audio_location' => 'append',
            'content_speech_text' => 'off',
            'speech_model' => 'whisper-1',
            'max_speech' => '',
            'audio_to_text' => '%%audio_to_text%%',
            'audio_to_text_prompt' => '',
            'speech_temperature' => '0',
            'audio_text_location' => 'append',
            'prep_audio' => '',
            'copy_location' => 'local',
            'auto_run_interval' => 'No'
        );
        if ($defaults === FALSE) {
            add_option('aiomatic_Spinner_Settings', $aiomatic_Spinner_Settings, '', false);
        } else {
            aiomatic_update_option('aiomatic_Spinner_Settings', $aiomatic_Spinner_Settings, false);
        }
    }
    if (!get_option('aiomatic_Chatbot_Settings') || $defaults === TRUE) {
        $aiomatic_Chatbot_Settings = array(
            'font_size' => '1em',
            'show_header' => 'show',
            'bubble_width' => 'full',
            'bubble_alignment' => 'left',
            'bubble_user_alignment' => 'right',
            'show_ai_avatar' => 'show',
            'show_user_avatar' => 'show',
            'user_account_avatar' => '',
            'chat_theme' => '',
            'show_dltxt' => 'show',
            'show_clear' => 'show',
            'show_mute' => 'show',
            'show_internet' => 'show',
            'voice_language' => 'en-US',
            'did_image' => 'https://create-images-results.d-id.com/api_docs/assets/noelle.jpeg',
            'did_height' => '300',
            'did_width' => '300',
            'did_voice' => 'microsoft:en-US-JennyNeural:Cheerful',
            'google_voice' => '',
            'audio_profile' => '',
            'voice_speed' => '1',
            'voice_pitch' => '0',
            'chatbot_text_speech' => 'off',
            'azure_voice' => 'en-US-AvaMultilingualNeural',
            'azure_voice_profile' => '',
            'azure_private_endpoint' => '',
            'azure_voice_endpoint' => '',
            'azure_region' => 'westus2',
            'azure_character' => 'lisa',
            'canvas_avatar_width' => '1200px',
            'azure_character_style' => 'casual-sitting',
            'free_voice' => 'Google US English;en-US',
            'voice_similarity_boost' => '',
            'voice_style' => '',
            'speaker_boost' => '',
            'open_model_id' => 'tts-1',
            'open_voice' => 'alloy',
            'open_format' => 'mp3',
            'open_speed' => '1',
            'voice_stability' => '',
            'eleven_model_id' => '',
            'eleven_voice' => '',
            'eleven_voice_custom' => '',
            'width' => '100%',
            'height' => 'auto',
            'minheight' => '250px',
            'custom_header' => '',
            'custom_footer' => '',
            'custom_css' => '',
            'background' => '#f7f7f9',
            'image_chat_size' => '512x512',
            'image_chat_model' => 'dalle2',
            'show_gdpr' => '',
            'gdpr_notice' => 'By using this chatbot, you consent to the collection and use of your data as outlined in our <a href=\'%%privacy_policy_url%%\' target=\'_blank\'>Privacy Policy</a>. Your data will only be used to assist with your inquiry.',
            'gdpr_checkbox' => 'I agree to the terms.',
            'gdpr_button' => 'Start chatting',
            'allow_chatbot_site' => '',
            'remote_chat' => '',
            'user_font_color' => '#ffffff',
            'user_background_color' => '#0084ff',
            'ai_font_color' => 'black',
            'ai_background_color' => '#f0f0f0',
            'input_border_color' => '#e1e3e6',
            'submit_color' => '#55a7e2',
            'submit_text_color' => '#ffffff',
            'voice_color' => '#55a7e2',
            'voice_color_activated' => '#55a7e2',
            'enable_moderation' => '',
            'moderation_model' => 'text-moderation-stable',
            'flagged_message' => 'Your message has been flagged as potentially harmful or inappropriate. Please review your language and content to ensure it aligns with our values of respect and sensitivity towards others. Thank you for your cooperation.',
            'enable_copy' => '',
            'chat_editing' => 'disabled',
            'enable_html' => 'on',
            'disable_modern_chat' => '',
            'allow_stream_stop' => '',
            'strip_js' => '',
            'scroll_bot' => '',
            'chat_waveform' => '',
            'waveform_color' => '',
            'send_message_sound' => '',
            'receive_message_sound' => '',
            'response_delay' => '',
            'instant_response' => 'stream',
            'voice_input' => '',
            'auto_submit_voice' => '',
            'chat_download_format' => 'txt',
            'chat_model' => aiomatic_get_default_model_name($aiomatic_Main_Settings),
            'temperature' => '1',
            'top_p' => '1',
            'max_tokens' => '',
            'presence_penalty' => '0',
            'frequency_penalty' => '0',
            'chat_preppend_text' => 'You are Ava, a friendly and knowledgeable AI chatbot companion. You are designed to provide information, assistance, and engaging conversations on a wide range of topics. Your goal is to make the user\'s experience enjoyable and informative, always prioritizing their comfort and privacy in every interaction. If users have questions or need assistance, they can feel free to ask, and you\'ll do your best to assist them in a friendly and respectful manner.',
            'ai_message_preppend' => 'Ava',
            'ai_role' => 'AI Chatbot',
            'user_message_preppend' => 'User',
            'ai_first_message' => 'Hi! How can I help you?',
            'assistant_id' => '',
            'chat_mode' => 'text',
            'user_token_cap_per_day' => '',
            'god_blacklisted_functions' => '',
            'god_mode_enable_wp' => '',
            'god_mode_enable_dalle' => '',
            'god_mode_enable_stable' => '',
            'god_mode_enable_midjourney' => '',
            'god_mode_enable_replicate' => '',
            'target_country' => 'com',
            'max_products' => '3-4',
            'sort_results' => 'none',
            'listing_template' => '%%product_counter%%. %%product_title%% - Desciption: %%product_description%% - Link: %%product_url%% - Price: %%product_price%%',
            'god_mode_enable_scraper' => '',
            'scrape_method' => '0',
            'strip_tags' => '',
            'max_chars' => '',
            'god_mode_enable_rss' => '',
            'max_rss_items' => '5',
            'rss_template' => '[%%item_counter%%]: %%item_title%% - %%item_description%%',
            'god_mode_enable_google' => '',
            'max_google_items' => '5',
            'google_template' => '[%%item_counter%%]: %%item_title%% - %%item_snippet%%',
            'god_mode_enable_youtube_captions' => '',
            'max_caption_length' => '1000',
            'god_mode_enable_royalty' => '',
            'god_mode_lead_capture' => '',
            'god_mode_enable_email' => '',
            'god_mode_enable_webhook' => '',
            'god_mode_enable_facebook_post' => '',
            'facebook_post_select' => '',
            'god_mode_enable_youtube' => '',
            'affiliate_id' => '',
            'stable_model' => '',
            'ai_image_size_stable' => '',
            'god_mode_dalle_failed' => '',
            'god_mode_stable_failed' => '',
            'god_mode_enable_stable_video' => '',
            'ai_video_size_stable' => '768x768',
            'god_mode_enable_amazon_details' => '',
            'god_mode_enable_amazon' => '',
            'ai_image_size' => '512x512',
            'ai_image_model' => 'dalle2',
            'god_whitelisted_functions' => '',
            'god_preview' => '',
            'god_mode_enable_twitter_post' => '',
            'god_mode_enable_instagram_post' => '',
            'god_mode_enable_pinterest_post' => '',
            'pinterest_post_select' => '',
            'god_mode_enable_google_post' => '',
            'god_mode_enable_youtube_post' => '',
            'god_mode_enable_reddit_post' => '',
            'subreddits_list' => '',
            'god_mode_enable_linkedin_post' => '',
            'linkedin_selected_pages' => '',
            'business_post_select' => array(),
            'god_mode_front_end' => 'off',
            'max_input_length' => '',
            'max_message_count' => '',
            'max_message_context' => '',
            'restriction_time' => '',
            'restriction_count' => '',
            'restriction_message' => 'You exceeded your requests limit. Please try again later.',
            'no_empty' => '',
            'persistent' => 'off',
            'persistent_guests' => 'off',
            'max_chat_log_not_login' => '',
            'max_chat_log_login' => '',
            'remember_chat_transient' => '',
            'enable_vision' => 'off',
            'enable_file_uploads' => 'off',
            'file_expiration_pdf' => '',
            'prompt_editable' => 'on',
            'file_expiration' => '',
            'prompt_templates' => '',
            'placeholder' => 'Enter your chat message here',
            'submit' => 'Submit',
            'compliance' => '',
            'select_prompt' => 'Please select a prompt',
            'upload_pdf' => '',
            'pdf_page' => '10',
            'pdf_character' => '',
            'pdf_ok' => 'PDF file uploaded successfully! You can ask questions about it.',
            'pdf_end' => 'PDF file session ended.',
            'pdf_fail' => 'Failed to upload the PDF file, please try again later.',
            'window_location' => 'bottom-right', 
            'page_load_chat' => '',
            'enable_front_end' => 'off',
            'custom_global_shortcode' => '',
            'window_width' => '400px',
            'not_show_urls' => '',
            'only_show_urls' => '',
            'max_time' => '',
            'min_time' => '',
            'always_show' => array(),
            'never_show' => array(),
            'show_content_wp' => array(),
            'no_show_content_wp' => array(),
            'no_show_locales' => array(),
            'show_locales' => array(),
            'no_show_roles' => array(),
            'show_roles' => array(),
            'no_show_devices' => array(),
            'show_devices' => array(),
            'no_show_oses' => array(),
            'show_oses' => array(),
            'no_show_browsers' => array(),
            'show_browsers' => array(),
            'no_show_ips' => '',
            'show_ips' => '',
            'chatbot_icon' => '1',
            'chatbot_icon_html' => ''
        );
        if ($defaults === FALSE) {
            add_option('aiomatic_Chatbot_Settings', $aiomatic_Chatbot_Settings, '', false);
        } else {
            aiomatic_update_option('aiomatic_Chatbot_Settings', $aiomatic_Chatbot_Settings, false);
        }
    }
    if (!get_option('aiomatic_Limit_Settings') || $defaults === TRUE) {
        $aiomatic_Limit_Settings = array(
            'user_credits' => '',
            'guest_credits' => '',
            'limit_message_not_logged' => 'You have reached the usage limit.',
            'limit_message_rule' => 'You have reached the usage limit.',
            'limit_message_logged' => 'You have reached the usage limit.',
            'ignored_users'  => 'admin',
            'user_credit_type' => 'units',
            'guest_credit_type' => 'queries',
            'user_time_frame' => 'month',
            'guest_time_frame' => 'day',
            'is_absolute_user' => '',
            'is_absolute_guest' => '',
            'enable_limits' => '',
            'enable_limits_text' => '',
            'block_userids' => '',
            'user_credits_text' => '',
            'user_credit_type_text' => 'characters',
            'user_time_frame_text' => '',
            'is_absolute_user_text' => '',
            'ignored_users_text' => '',
            'guest_credits_text' => '',
            'guest_credit_type_text' => 'characters',
            'guest_time_frame_text' => '',
            'is_absolute_guest_text' => '',
            'additional_roles' => array()
        );
        if ($defaults === FALSE) {
            add_option('aiomatic_Limit_Settings', $aiomatic_Limit_Settings, '', false);
        } else {
            aiomatic_update_option('aiomatic_Limit_Settings', $aiomatic_Limit_Settings, false);
        }
    }
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    aiomatic_register_aggregated_feed_table();
    $sql_create_table = "CREATE TABLE IF NOT EXISTS {$wpdb->aiomatict_shortcode_rez} (
          post_id bigint(20) unsigned NOT NULL auto_increment,
          post_hash text default '',
          post_result text default '',
          PRIMARY KEY  (post_id)
     ) $charset_collate; ";
    
    dbDelta( $sql_create_table );
}
add_action( 'pre_user_query', 'aiomatic_random_user_query' );

function aiomatic_get_eleven_voices()
{
    $default_voices = array(
        '21m00Tcm4TlvDq8ikWAM' => 'Rachel',
        'AZnzlk1XvdvUeBnXmlld' => 'Domi',
        'EXAVITQu4vr4xnSDxMaL' => 'Bella',
        'ErXwobaYiN019PkySvjV' => 'Antoni',
        'MF3mGyEYCl7XYWbV9V6O' => 'Elli',
        'TxGEqnHWrfWFTfGW9XjX' => 'Josh',
        'VR6AewLTigWG4xSOukaG' => 'Arnold',
        'pNInz6obpgDQGcFmaJgB' => 'Adam',
        'yoZ06aMxZJJ28mfd3POQ' => 'Sam'
    );
    $aiomatic_elevenlabs = get_option('aiomatic_elevenlabs', false);
	if(is_array($aiomatic_elevenlabs))
	{
		return array_merge($aiomatic_elevenlabs, $default_voices);
	}
    $aiomatic_elevenlabs = aiomatic_update_elevenlabs_voices();
    if(is_array($aiomatic_elevenlabs))
    {
        aiomatic_update_option('aiomatic_elevenlabs', $aiomatic_elevenlabs, false);
        return array_merge($aiomatic_elevenlabs, $default_voices);
    }
	return $default_voices;
}

function aiomatic_get_google_voices($language)
{
    $aiomatic_elevenlabs = get_option('aiomatic_google_voices' . sanitize_title($language), false);
	if(is_array($aiomatic_elevenlabs))
	{
		return $aiomatic_elevenlabs;
	}
    $aiomatic_elevenlabs = aiomatic_update_google_voices($language);
    if(is_array($aiomatic_elevenlabs))
    {
        aiomatic_update_option('aiomatic_google_voices' . sanitize_title($language), $aiomatic_elevenlabs, false);
        return $aiomatic_elevenlabs;
    }
	return false;
}
function aiomatic_elevenlabs_stream($voice, $text, $option = 'aiomatic_Chatbot_Settings')
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if ((!isset($aiomatic_Main_Settings['elevenlabs_app_id']) || trim($aiomatic_Main_Settings['elevenlabs_app_id']) == ''))
    {
        $aiomatic_result = array('status' => 'error', 'msg' => 'Missing ElevenLabs API key');
        return $aiomatic_result;
    }
    else
    {
        $aiomatic_Chatbot_Settings = get_option($option, false);
        $text = str_replace("\\",'',$text);
        $text = apply_filters('aiomatic_modify_ai_voice_text', $text);
        if (isset($aiomatic_Chatbot_Settings['voice_similarity_boost']) && trim($aiomatic_Chatbot_Settings['voice_similarity_boost']) != '')
        {
            $voice_similarity_boost = floatval($aiomatic_Chatbot_Settings['voice_similarity_boost']);
        }
        else
        {
            $voice_similarity_boost = 0.75;
        }
        if (isset($aiomatic_Chatbot_Settings['voice_stability']) && trim($aiomatic_Chatbot_Settings['voice_stability']) != '')
        {
            $voice_stability = floatval($aiomatic_Chatbot_Settings['voice_stability']);
        }
        else
        {
            $voice_stability = 0.75;
        }
        if (isset($aiomatic_Chatbot_Settings['eleven_model_id']) && trim($aiomatic_Chatbot_Settings['eleven_model_id']) != '')
        {
            $eleven_model_id = $aiomatic_Chatbot_Settings['eleven_model_id'];
        }
        else
        {
            $eleven_model_id = '';
        }
        if (isset($aiomatic_Chatbot_Settings['voice_style']) && trim($aiomatic_Chatbot_Settings['voice_style']) != '')
        {
            $voice_style = $aiomatic_Chatbot_Settings['voice_style'];
        }
        else
        {
            $voice_style = '';
        }
        $voice_settings = array('stability' => $voice_stability, 'similarity_boost' => $voice_similarity_boost);
        if (isset($aiomatic_Chatbot_Settings['speaker_boost']) && trim($aiomatic_Chatbot_Settings['speaker_boost']) == 'on')
        {
            $voice_settings['use_speaker_boost'] = true;
        }
        if($voice_style != '')
        {
            $voice_settings['style'] = floarval($voice_style);
        }
        $rqbody = array('text' => $text, 'voice_settings' => $voice_settings);
        if($eleven_model_id != '')
        {
            $rqbody['model_id'] = $eleven_model_id;
        }
        $response = wp_remote_post('https://api.elevenlabs.io/v1/text-to-speech/' . $voice . '/stream', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'xi-api-key' => trim($aiomatic_Main_Settings['elevenlabs_app_id'])
            ),
            'body' => json_encode($rqbody),
            'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
        ));
        if(is_wp_error($response))
        {
            $aiomatic_result = array('status' => 'error', 'msg' => $response->get_error_message());
            return $aiomatic_result;
        }
        else
        {
            return wp_remote_retrieve_body($response);
        }
    }
}
function aiomatic_openai_voice_stream($token, $open_model_id, $open_voice, $open_format, $open_speed, $message)
{
    if(empty($token))
    {
        $aiomatic_result = array('status' => 'error', 'msg' => 'You need to enter a valid OpenAI API key for TTS to work.');
        return $aiomatic_result;
    }
    if(empty($open_model_id))
    {
        $open_model_id = 'tts-1'; 
    }
    if(empty($open_voice))
    {
        $open_voice = 'alloy'; 
    }
    if(empty($open_format))
    {
        $open_format = 'mp3'; 
    }
    if(empty($open_speed))
    {
        $open_speed = '1'; 
    }
    if(strlen($message) > 4096 && ($open_model_id == 'tts-1' || $open_model_id == 'tts-1-hd'))
    {
        $message = substr($message, 0, 4096);
    }
    $message = str_replace("\\", '', $message);
    $message = apply_filters('aiomatic_modify_ai_voice_text', $message);
    require_once (dirname(__FILE__) . "/res/openai/Url.php"); 
    require_once (dirname(__FILE__) . "/res/openai/OpenAi.php");
    $open_ai = new OpenAi($token);
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['openai_organization']) && $aiomatic_Main_Settings['openai_organization'] != '')
    {
        $open_ai->setORG($aiomatic_Main_Settings['openai_organization']);
    }
    $opts = 
    [
        'tts' => true,
        'model' => $open_model_id,
        'input' => $message,
        'voice' => $open_voice,
        'response_format' => $open_format,
        'speed' => $open_speed
    ];
    $audioData = $open_ai->createSpeech($opts);
    return $audioData;
}
function aiomatic_google_stream($voice, $voice_language, $audio_profile, $voice_speed, $voice_pitch, $text)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $aiomatic_result = array('status' => 'error', 'msg' => 'Something went wrong with Google Stream');
    if ((!isset($aiomatic_Main_Settings['google_app_id']) || trim($aiomatic_Main_Settings['google_app_id']) == ''))
    {
        $aiomatic_result = array('status' => 'error', 'msg' => 'Missing Google Text-to-Speech API key');
        return $aiomatic_result;
    }
    else
    {
        if(empty($voice_pitch))
        {
            $voice_pitch = '0';
        }
        if(empty($voice_speed))
        {
            $voice_speed = '1';
        }
        $text = str_replace("\\",'',$text);
        $text = apply_filters('aiomatic_modify_ai_voice_text', $text);
        $params = array(
            'audioConfig' => array(
                'audioEncoding' => 'LINEAR16',
                'pitch' => $voice_pitch,
                'speakingRate' => $voice_speed,
            ),
            'input' => array(
                'text' => $text
            ),
            'voice' => array(
                'languageCode' => $voice_language,
                'name' => $voice
            )
        );
        if(!empty($audio_profile)){
            $params['audioConfig']['effectsProfileId'] = array($audio_profile);
        }
        $response = wp_remote_post('https://texttospeech.googleapis.com/v1/text:synthesize?fields=audioContent&key=' . trim($aiomatic_Main_Settings['google_app_id']), array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($params),
            'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
        ));
        if(is_wp_error($response))
        {
            $aiomatic_result = array('status' => 'error', 'msg' => $response->get_error_message());
            return $aiomatic_result;
        }
        else
        {
            $body = wp_remote_retrieve_body($response);
            $body = json_decode($body, true);
            if(isset($body['error'])){
                $aiomatic_result['msg'] = $body['error']['message'];
            }
            elseif(isset($body['audioContent']) && !empty($body['audioContent'])){
                $aiomatic_result['audio'] = $body['audioContent'];
                $aiomatic_result['status'] = 'success';
            }
            else{
                $aiomatic_result['msg'] = esc_html__('Google did not generate any audio for this text','aiomatic-automatic-ai-content-writer');
            }
        }
    }
    return $aiomatic_result;
}

function aiomatic_d_id_video($did_image, $text, $did_voice)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $aiomatic_result = array('status' => 'error', 'msg' => 'Something went wrong with D-ID API');
    if ((!isset($aiomatic_Main_Settings['did_app_id']) || trim($aiomatic_Main_Settings['did_app_id']) == ''))
    {
        $aiomatic_result = array('status' => 'error', 'msg' => 'Missing D-ID API key');
        return $aiomatic_result;
    }
    else
    {
        $text = str_replace("\\",'',$text);
        $text = apply_filters('aiomatic_modify_ai_video_text', $text);
        $script = array(
            'type' => 'text',
            'input' => html_entity_decode($text, ENT_QUOTES)
        );
        $did_voice_exp = explode(':', $did_voice);
        if(isset($did_voice_exp[1]))
        {
            if(trim($did_voice_exp[0]) != '')
            {
                $script['provider'] = array('type' => strtolower($did_voice_exp[0]), 'voice_id' => trim($did_voice_exp[1]));
                if(isset($did_voice_exp[2]))
                {
                    $script['provider']['voice_config']['style'] = trim($did_voice_exp[2]);
                }
            }
        }
        $params = array(
            'source_url' => $did_image,
            'script' => $script
        );
        $response = wp_remote_post('https://api.d-id.com/talks', array(
            'headers' => array(
                'authorization' => 'Basic ' . trim($aiomatic_Main_Settings['did_app_id']),
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
            'body' => json_encode($params),
            'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
        ));
        if(is_wp_error($response))
        {
            $aiomatic_result = array('status' => 'error', 'msg' => $response->get_error_message());
            return $aiomatic_result;
        }
        else
        {
            $body_resp = wp_remote_retrieve_body($response);
            $body = json_decode($body_resp, true);
            if($body === null)
            {
                $aiomatic_result['msg'] = 'Failed to decode response: ' . print_r($body_resp, true);
                return $aiomatic_result;
            }
            if(!isset($body['id']) || empty($body['id']))
            {
                $aiomatic_result['msg'] = 'Video ID not found in response: ' . print_r($body, true);
                return $aiomatic_result;
            }
            $idone = false;
            $retried = 0;
            sleep(3);
            while($idone === false && $retried < 50)
            {
                $presponse = wp_remote_get('https://api.d-id.com/talks/' . $body['id'], array(
                    'headers' => array(
                        'authorization' => 'Basic ' . trim($aiomatic_Main_Settings['did_app_id']),
                        'accept' => 'application/json',
                        'content-type' => 'application/json'
                    ),
                    'timeout' => 1000
                ));
                if(is_wp_error($presponse))
                {
                    $aiomatic_result = array('status' => 'error', 'msg' => 'Polling failed: ' . $presponse->get_error_message());
                    return $aiomatic_result;
                }
                else
                {
                    $pbody_resp = wp_remote_retrieve_body($presponse);
                    $pbody = json_decode($pbody_resp, true);
                    if($pbody === null)
                    {
                        $aiomatic_result['msg'] = 'Failed to decode polling response: ' . print_r($pbody_resp, true);
                        return $aiomatic_result;
                    }
                    if(!isset($pbody['status']))
                    {
                        if(!isset($pbody['message']) && $pbody['message'] == 'Too Many Requests')
                        {
                            sleep(3);
                            continue;
                        }
                        else
                        {
                            $aiomatic_result['msg'] = 'Failed to interpret polling response: ' . print_r($pbody, true);
                            return $aiomatic_result;
                        }
                    }
                    if($pbody['status'] == 'done')
                    {
                        if(isset($pbody['result_url']))
                        {
                            $aiomatic_result['video'] = $pbody['result_url'];
                            $aiomatic_result['status'] = 'success';
                            $idone = true;
                        }
                        else
                        {
                            $aiomatic_result['msg'] = 'Failed to detect result URL: ' . print_r($pbody, true);
                            return $aiomatic_result;
                        }
                    }
                    elseif($pbody['status'] == 'created' || $pbody['status'] == 'started')
                    {
                        sleep(3);
                    }
                    else
                    {
                        $aiomatic_result['msg'] = 'Failed to interpret polling status: ' . print_r($pbody, true);
                        return $aiomatic_result;
                    }
                }
                $retried++;
            }
        }
    }
    return $aiomatic_result;
}

function aiomatic_d_id_idle_video($did_image, $text)
{
    $transient_key = 'aiomatic_did_avatar_' . md5($did_image . $text);
    $cached_response = get_transient($transient_key);
    if ($cached_response !== false) 
    {
        return $cached_response;
    }
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $aiomatic_result = array('status' => 'error', 'msg' => 'Something went wrong with D-ID API');
    if ((!isset($aiomatic_Main_Settings['did_app_id']) || trim($aiomatic_Main_Settings['did_app_id']) == ''))
    {
        $aiomatic_result = array('status' => 'error', 'msg' => 'Missing D-ID API key');
        return $aiomatic_result;
    }
    else
    {
        $script = array(
            'type' => 'text',
            'ssml' => true,
            'input' => $text
        );
        $params = array(
            'source_url' => $did_image,
            'script' => $script,
            'config' => array(
                'fluent' => true,
                'stitch' => true
            ),
            'driver_url' => 'bank://lively/'
        );
        $response = wp_remote_post('https://api.d-id.com/talks', array(
            'headers' => array(
                'authorization' => 'Basic ' . trim($aiomatic_Main_Settings['did_app_id']),
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
            'body' => json_encode($params),
            'timeout' => AIOMATIC_DEFAULT_BIG_TIMEOUT
        ));
        if(is_wp_error($response))
        {
            $aiomatic_result = array('status' => 'error', 'msg' => $response->get_error_message());
            return $aiomatic_result;
        }
        else
        {
            $body_resp = wp_remote_retrieve_body($response);
            $body = json_decode($body_resp, true);
            if($body === null)
            {
                $aiomatic_result['msg'] = 'Failed to decode response: ' . print_r($body_resp, true);
                return $aiomatic_result;
            }
            if(!isset($body['id']) || empty($body['id']))
            {
                $aiomatic_result['msg'] = 'Video ID not found in video response: ' . print_r($body_resp, true);
                return $aiomatic_result;
            }
            $idone = false;
            $retried = 0;
            sleep(3);
            while($idone === false && $retried < 50)
            {
                $presponse = wp_remote_get('https://api.d-id.com/talks/' . $body['id'], array(
                    'headers' => array(
                        'authorization' => 'Basic ' . trim($aiomatic_Main_Settings['did_app_id']),
                        'accept' => 'application/json',
                        'content-type' => 'application/json'
                    ),
                    'timeout' => 1000
                ));
                if(is_wp_error($presponse))
                {
                    $aiomatic_result = array('status' => 'error', 'msg' => 'Polling failed: ' . $presponse->get_error_message());
                    return $aiomatic_result;
                }
                else
                {
                    $pbody_resp = wp_remote_retrieve_body($presponse);
                    $pbody = json_decode($pbody_resp, true);
                    if($pbody === null)
                    {
                        $aiomatic_result['msg'] = 'Failed to decode polling response: ' . print_r($pbody_resp, true);
                        return $aiomatic_result;
                    }
                    if(!isset($pbody['status']))
                    {
                        if(!isset($pbody['message']) && $pbody['message'] == 'Too Many Requests')
                        {
                            sleep(3);
                            continue;
                        }
                        else
                        {
                            $aiomatic_result['msg'] = 'Failed to interpret polling response: ' . print_r($pbody, true);
                            return $aiomatic_result;
                        }
                    }
                    if($pbody['status'] == 'done')
                    {
                        if(isset($pbody['result_url']))
                        {
                            $aiomatic_result['video'] = $pbody['result_url'];
                            $aiomatic_result['status'] = 'success';
                            $idone = true;
                            set_transient($transient_key, $aiomatic_result, 86400);
                        }
                        else
                        {
                            $aiomatic_result['msg'] = 'Failed to detect result URL: ' . print_r($pbody, true);
                            return $aiomatic_result;
                        }
                    }
                    elseif($pbody['status'] == 'created' || $pbody['status'] == 'started')
                    {
                        sleep(3);
                    }
                    else
                    {
                        $aiomatic_result['msg'] = 'Failed to interpret polling status: ' . print_r($pbody, true);
                        return $aiomatic_result;
                    }
                }
                $retried++;
            }
        }
    }
    return $aiomatic_result;
}

function aiomatic_update_elevenlabs_voices()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (!isset($aiomatic_Main_Settings['elevenlabs_app_id']) || trim($aiomatic_Main_Settings['elevenlabs_app_id']) == '')
    {
        return false;
    }
    $response = wp_remote_get('https://api.elevenlabs.io/v1/voices', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'xi-api-key' => trim($aiomatic_Main_Settings['elevenlabs_app_id'])
        )
    ));
    if(!is_wp_error($response))
    {
        $body = json_decode(wp_remote_retrieve_body($response),true);
        if($body === null)
        {
            aiomatic_log_to_file('Failed to decode response: ' . print_r($response, true));
            return false;
        }
        else
        {
            if(is_array($body) && isset($body['voices']) && is_array($body['voices']))
            {
                $option_voices = [];
                foreach($body['voices'] as $voice){
                    $option_voices[$voice['voice_id']] = $voice['name'];
                }
                return $option_voices;
            }
            else
            {
                aiomatic_log_to_file('Error while listing voices: ' . print_r($body, true));
                return false;
            }
        }
    }
    else
    {
        aiomatic_log_to_file('Failed to list ElevenLabs voices: ' . $response->get_error_message());
        return false;
    }
}
function aiomatic_update_google_voices($language)
{
    if(empty($language))
    {
        $language = 'en-US';
    }
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (!isset($aiomatic_Main_Settings['google_app_id']) || trim($aiomatic_Main_Settings['google_app_id']) == '')
    {
        aiomatic_log_to_file('You need to enter an Google Text-to-Speech API key in the plugin\'s settings to use this feature');
        return false;
    }
    $response = wp_remote_get('https://texttospeech.googleapis.com/v1/voices?languageCode=' . $language . '&key=' . trim($aiomatic_Main_Settings['google_app_id']));
    if(!is_wp_error($response))
    {
        $body = json_decode(wp_remote_retrieve_body($response),true);
        if($body === null)
        {
            aiomatic_log_to_file('Failed to decode response: ' . print_r($response, true));
            return false;
        }
        else
        {
            if(is_array($body) && isset($body['voices']) && is_array($body['voices']))
            {
                return $body['voices'];
            }
            else
            {
                aiomatic_log_to_file('Error while listing voices: ' . print_r($body, true));
                return false;
            }
        }
    }
    else
    {
        aiomatic_log_to_file('Failed to list Google Text-to-Speech voices: ' . $response->get_error_message());
        return false;
    }
}
function aiomatic_random_user_query( $class ) {
    if( 'rand' == $class->query_vars['orderby'] )
        $class->query_orderby = str_replace( 'user_login', 'RAND()', $class->query_orderby );

    return $class;
}
register_deactivation_hook(__FILE__,'aiomatic_deactivate_plugin');
function aiomatic_deactivate_plugin()
{
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS $wpdb->aiomatict_shortcode_rez");
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['clear_omni']) && $aiomatic_Main_Settings['clear_omni'] == 'on')
    {
        delete_option('aiomatic_processed_keywords');
    }
}
function aiomatic_get_item_table_columns(){
    return array(
        'post_id'=> '%d',
        'post_hash' => '%s',
        'post_result'=> '%s'
    );
}
add_action( 'init', 'aiomatic_register_aggregated_feed_table', 1 );
add_action( 'switch_blog', 'aiomatic_register_aggregated_feed_table' );
function aiomatic_insert_item($data=array()){
    global $wpdb;        
    $data = wp_parse_args($data, array(
                 'post_hash'=> '',
                 'post_result'=> ''
    ));
    $column_formats = aiomatic_get_item_table_columns();
    $data = array_change_key_case ( $data );
    $data = array_intersect_key($data, $column_formats);
    $data_keys = array_keys($data);
    $column_formats = array_merge(array_flip($data_keys), $column_formats);
    add_filter('query', 'aiomatic_modifyInsertQuery', 10);
    $wpdb->insert($wpdb->aiomatict_shortcode_rez, $data, $column_formats);
    remove_filter('query', 'aiomatic_modifyInsertQuery', 10);
    if($wpdb->insert_id == 0)
    {
        if($wpdb->last_error != '')
        {
            $query = htmlspecialchars( print_r($wpdb->last_query, true), ENT_QUOTES );
            aiomatic_log_to_file('WordPress database error: "' . $wpdb->last_error . '" QUERY: ' . $query);
        }
    }
    return $wpdb->insert_id;
}
function aiomatic_modifyInsertQuery( $query ){
    $count 	= 0;
	$query 	= preg_replace('/^(INSERT INTO)/i', 'INSERT IGNORE INTO', $query, 1 , $count );
	return $query;
}
function aiomatic_register_aggregated_feed_table() {
    global $wpdb;
    $wpdb->aiomatict_shortcode_rez = "{$wpdb->prefix}aiomatict_shortcode_rez";
}

register_activation_hook(__FILE__, 'aiomatic_check_version');
function aiomatic_check_version()
{
    if (!function_exists('curl_init')) {
        echo '<h3>'.esc_html__('Please enable curl PHP extension. Please contact your hosting provider\'s support to help you in this matter.', 'aiomatic-automatic-ai-content-writer').'</h3>';
        die;
    }
    global $wp_version;
    if (!current_user_can('activate_plugins')) {
        echo '<p>' . esc_html__('You are not allowed to activate plugins!', 'aiomatic-automatic-ai-content-writer') . '</p>';
        die;
    }
    $php_version_required = '5.0';
    $wp_version_required  = '2.7';
    
    if (version_compare(PHP_VERSION, $php_version_required, '<')) {
        deactivate_plugins(basename(__FILE__));
        echo '<p>' . sprintf(esc_html__('This plugin can not be activated because it requires a PHP version greater than %1$s. Please update your PHP version before you activate it.', 'aiomatic-automatic-ai-content-writer'), $php_version_required) . '</p>';
        die;
    }
    
    if (version_compare($wp_version, $wp_version_required, '<')) {
        deactivate_plugins(basename(__FILE__));
        echo '<p>' . sprintf(esc_html__('This plugin can not be activated because it requires a WordPress version greater than %1$s. Please go to Dashboard -> Updates to get the latest version of WordPress.', 'aiomatic-automatic-ai-content-writer'), $wp_version_required) . '</p>';
        die;
    }
}

function aiomatic_process_replicate_images($url, $headers)
{
    $images = array();
    $response = wp_remote_get($url, array('headers' => $headers));
    if(is_wp_error($response)){
        throw new Exception('Error in Replicate access: ' . $response->get_error_message());
    }
    else{
        $body = json_decode($response['body'],true);
        if($body['status'] == 'succeeded'){
            $images = $body['output'];
        }
        elseif($body['status'] == 'processing' || $body['status'] == 'starting'){
            sleep(1);
            $images = aiomatic_process_replicate_images($url, $headers);
        }
        elseif($body['status'] == 'failed'){
            throw new Exception('Replicate returned and error: ' . $body['error']);
        }
        else{
            throw new Exception('Replicate - something went wrong');
        }
    }
    return $images;
}

function aiomatic_resizeImageStringToMultipleOf64($imageString, $stable_model) 
{
    $allowedDimensions = [
        'stable-diffusion-xl-1024-v0-9' => [
            [1024, 1024], [1152, 896], [1216, 832], [1344, 768], [1536, 640],
            [640, 1536], [768, 1344], [832, 1216], [896, 1152]
        ],
        'stable-diffusion-xl-1024-v1-0' => [
            [1024, 1024], [1152, 896], [1216, 832], [1344, 768], [1536, 640],
            [640, 1536], [768, 1344], [832, 1216], [896, 1152]
        ]
    ];
    $maxPixels = 1048576;
    $minPixels = 262144;
    $factor = 64;
    if(!function_exists('imagecreatefromstring'))
    {
        return false;
    }
    $srcImage = imagecreatefromstring($imageString);
    if (!$srcImage) 
    {
        return false;
    }
    $width = imagesx($srcImage);
    $height = imagesy($srcImage);
    $newWidth = round($width / $factor) * $factor;
    $newHeight = round($height / $factor) * $factor;
    while (($newWidth * $newHeight) > $maxPixels) 
    {
        $newWidth -= $factor;
        $newHeight -= $factor;
    }
    while (($newWidth * $newHeight) < $minPixels) 
    {
        $newWidth += $factor;
        $newHeight += $factor;
    }
    if (array_key_exists($stable_model, $allowedDimensions)) 
    {
        $closest = null;
        $closestDiff = PHP_INT_MAX;
        foreach ($allowedDimensions[$stable_model] as list($allowedWidth, $allowedHeight)) 
        {
            $diff = abs($newWidth - $allowedWidth) + abs($newHeight - $allowedHeight);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closest = [$allowedWidth, $allowedHeight];
            }
        }
        if ($closest !== null) 
        {
            list($resizeWidth, $resizeHeight) = $closest;
        } 
        else 
        {
            $resizeWidth = $newWidth;
            $resizeHeight = $newHeight;
        }
    } 
    else 
    {
        $resizeWidth = $newWidth;
        $resizeHeight = $newHeight;
    }
    $newImage = imagecreatetruecolor($resizeWidth, $resizeHeight);
    imagecopyresampled($newImage, $srcImage, 0, 0, 0, 0, $resizeWidth, $resizeHeight, $width, $height);
    ob_start();
    imagejpeg($newImage);
    $newImageString = ob_get_clean();
    imagedestroy($srcImage);
    imagedestroy($newImage);
    return $newImageString;
}
function aiomatic_transformFileName($url) 
{
    $path = parse_url($url, PHP_URL_PATH);
    $originalFileName = pathinfo($path, PATHINFO_FILENAME);
    return $originalFileName;
}
function aiomatic_list_stability_engines()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (!isset($aiomatic_Main_Settings['stability_app_id']) || trim($aiomatic_Main_Settings['stability_app_id']) == '') 
    {
        aiomatic_log_to_file('You need to enter a Stability.AI API key in the plugin\'s "Settings" menu to use this feature!');
        return false;
    }
    $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['stability_app_id']));
    $appids = array_filter($appids);
    $token = $appids[array_rand($appids)];
    $token = apply_filters('aiomatic_stability_api_key', $token);
    $api_url = 'https://api.stability.ai/v1/engines/list';
    $ch = curl_init();
    if($ch === false)
    {
        aiomatic_log_to_file('Failed to create Stability curl request.');
        return false;
    }
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: ' . $token));
    $ai_response = curl_exec($ch);
    $info = curl_getinfo($ch);
    if($info['http_code'] != 200)
    {
        aiomatic_log_to_file('Invalid return code from API: ' . $info['http_code'] . ' response: ' . print_r($ai_response, true));
        return false;
    }
    curl_close($ch);
    if($ai_response === false)
    {
        aiomatic_log_to_file('Failed to get AI response: ' . $api_url);
        return false;
    }
    else
    {
        $json_resp = json_decode($ai_response, true);
        if($json_resp === null)
        {
            aiomatic_log_to_file('Failed to decode AI response: ' . $ai_response);
            return false;
        }
        aiomatic_log_to_file('Results: ' . print_r($json_resp, true));
    }
    return true;
}
add_filter( 'aiomatic_replace_aicontent_shortcode', 'aiomatic_ai_content_replace', 10, 1 );
function aiomatic_ai_content_replace($content)
{
    $content = aiomatic_do_aicontent_shortcode($content, false);
    return $content;
}
function aiomatic_do_aicontent_shortcode( $content, $ignore_html = false ) 
{
    //do other shortcodes
    global $shortcode_tags;
    $tagnames = $shortcode_tags;
    if (!function_exists('str_contains')) 
    {
        function str_contains($haystack, $needle) 
        {
            if(function_exists('mb_strpos'))
            {
                return $needle !== '' && mb_strpos($haystack, $needle) !== false;
            }
            else
            {
                return $needle !== '' && strpos($haystack, $needle) !== false;
            }
        }
    }
    if ( !str_contains( $content, '[' ) || !str_contains( $content, 'aicontent' ) ) {
        return $content;
    }
    if ( empty( $tagnames ) || ! is_array( $tagnames ) ) {
        $tagnames = array();
    }
    $tagnames = array_keys( $tagnames );
    if (($key = array_search('aicontent', $tagnames)) !== false) 
    {
        unset($tagnames[$key]);
    }
    $has_filter   = has_filter( 'wp_get_attachment_image_context', '_filter_do_shortcode_context' );
    $filter_added = false;
    if ( ! $has_filter ) {
        $filter_added = add_filter( 'wp_get_attachment_image_context', '_filter_do_shortcode_context' );
    }
    $content = do_shortcodes_in_html_tags( $content, $ignore_html, $tagnames );
    $pattern = get_shortcode_regex( $tagnames );
    $content = preg_replace_callback( "/$pattern/", 'do_shortcode_tag', $content );
    $content = unescape_invalid_shortcodes( $content );
    if ( $filter_added ) {
        remove_filter( 'wp_get_attachment_image_context', '_filter_do_shortcode_context' );
    }
    //now do aicontent alone
    $tagnames = array('aicontent');
    if ( ! str_contains( $content, '[' ) || !str_contains( $content, 'aicontent' ) ) {
        return $content;
    }
    $has_filter   = has_filter( 'wp_get_attachment_image_context', '_filter_do_shortcode_context' );
    $filter_added = false;
    if ( ! $has_filter ) {
        $filter_added = add_filter( 'wp_get_attachment_image_context', '_filter_do_shortcode_context' );
    }
    $content = do_shortcodes_in_html_tags( $content, $ignore_html, $tagnames );
    $pattern = get_shortcode_regex( $tagnames );
    $content = preg_replace_callback( "/$pattern/", 'do_shortcode_tag', $content );
    $content = unescape_invalid_shortcodes( $content );
    if ( $filter_added ) {
        remove_filter( 'wp_get_attachment_image_context', '_filter_do_shortcode_context' );
    }
    return $content;
}
function aiomatic_add_custom_capability() 
{
    if (is_admin()) 
    {
        $role_names = array('administrator'); 
        $aiomatic_Limit_Settings = get_option('aiomatic_Limit_Settings', array());
        if(isset($aiomatic_Limit_Settings['additional_roles']) && is_array($aiomatic_Limit_Settings['additional_roles']))
        {
            $role_names = array_merge($role_names, $aiomatic_Limit_Settings['additional_roles']);
        }
        $all_roles = wp_roles()->roles;
        foreach ($all_roles as $role_name => $role_info) 
        {
            if (!in_array($role_name, $role_names)) 
            {
                $role = get_role($role_name);
                if ($role && $role->has_cap('access_aiomatic_menu')) 
                {
                    $role->remove_cap('access_aiomatic_menu');
                }
            }
        }
        foreach ($role_names as $role_name) 
        {
            $role = get_role($role_name);
            if ($role && !$role->has_cap('access_aiomatic_menu')) 
            {
                $role->add_cap('access_aiomatic_menu');
            }
        }
    }
}
add_action('plugins_loaded', 'aiomatic_add_custom_capability');
add_action('admin_init', 'aiomatic_setup_wizard_screen');
function aiomatic_setup_wizard_screen()
{
    if ( !current_user_can('administrator') || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( 'access_aiomatic_menu' ) || aiomatic_is_demo_server()) 
    {
        return;
    }
    //0 not run
    //1 canceled
    //2 running
    //3 completed
    $is_ran = get_option( 'aiomatic_setup_wizard_ran', '0' );
    if($is_ran == '0')
    {
        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
        if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '')
        {
            $is_ran = '1';
            aiomatic_update_option( 'aiomatic_setup_wizard_ran', '1', false );
        }
    }
    if (is_admin()) 
    {
        global $pagenow;
        if(isset($_GET['aiomatic_go_config']) && $_GET['aiomatic_go_config'] == '1' && isset($_GET['nonce']) && wp_verify_nonce( $_GET['nonce'], 'aiomatic-quick-config' ) !== false)
        {
            $is_ran = '0';
        }
        if ((($pagenow == 'admin.php' && !isset($_GET['page'])) || ($pagenow == 'admin.php' && $_GET['page'] == 'aiomatic_admin_settings')) && isset($_GET['aiomatic_done_config'])) 
        {
            if($is_ran == '2')
            {
                if($_GET['aiomatic_done_config'] == '1')
                {
                    aiomatic_update_option( 'aiomatic_setup_wizard_ran', '1', false );
                    $is_ran = '1';
                }
                elseif($_GET['aiomatic_done_config'] == '3')
                {
                    aiomatic_update_option( 'aiomatic_setup_wizard_ran', '3', false );
                    $is_ran = '3';
                }
            }
        }
    }
    if(!isset($_GET['skip_config']) || $_GET['skip_config'] != '1')
    {
        if ( $is_ran != '1' && $is_ran != '2' && $is_ran != '3' ) {
            aiomatic_update_option( 'aiomatic_setup_wizard_ran', '2', false );
            wp_safe_redirect( admin_url( 'admin.php?page=aiomatic_admin_settings' ) );
            exit;
        }
        elseif($is_ran == '2')
        {
            require_once (dirname(__FILE__) . "/class-setup-wizard.php");
            new Aiomatic_Setup_Wizard();
        }
    }
}
function aiomatic_license_not_activated_notice() {
?>
    <div class="notice notice-error is-dismissible">
        <p><?php 
        $result = sprintf( wp_kses( __( 'The Aiomatic plugin\'s license is not activated. Please <a href="%1$s" target="_blank">activate the license</a> to use the plugin. You can get a new license, <a href="%2$s" target="_blank">here</a>.', 'aiomatic-automatic-ai-content-writer'), array(  'a' => array( 'href' => array(), 'target' => array() ) ) ), admin_url('admin.php?page=aiomatic_admin_settings'), 'https://1.envato.market/aiomatic');
        echo $result; ?></p>
    </div>
<?php
}
add_action('admin_init', 'aiomatic_register_mysettings');
function aiomatic_register_mysettings()
{
    if(!is_multisite() || is_main_site())
    {
        $plugin = plugin_basename(__FILE__);
        $plugin_slug = explode('/', $plugin);
        $plugin_slug = $plugin_slug[0];
        $uoptions = array();
        $is_activated = aiomatic_is_activated($plugin_slug, $uoptions);
        if($is_activated === true || $is_activated === 2)
        {
            require "update-checker/plugin-update-checker.php";
            $fwdu3dcarPUC = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker("https://wpinitiate.com/auto-update/?action=get_metadata&slug=aiomatic-automatic-ai-content-writer", __FILE__, "aiomatic-automatic-ai-content-writer");
        }
        else
        {
            add_action('admin_notices', 'aiomatic_license_not_activated_notice');
            add_action("after_plugin_row_{$plugin}", function( $plugin_file, $plugin_data, $status ) {
                $plugin_url = 'https://codecanyon.net/item/aiomatic-automatic-ai-content-writer/38877369';
                echo '<tr class="active"><td>&nbsp;</td><td colspan="2"><p class="cr_auto_update">';
            echo sprintf( wp_kses( __( 'The plugin is not registered. Automatic updating is disabled. Please purchase a license for it from <a href="%1$s" target="_blank">here</a> and register  the plugin from the \'Settings\' menu using your purchase code. <a href="%2$s" target="_blank">How I find my purchase code?', 'aiomatic-automatic-ai-content-writer'), array(  'a' => array( 'href' => array(), 'target' => array() ) ) ), esc_url_raw( 'https://1.envato.market/c/1264868/275988/4415?u=' . urlencode($plugin_url)), '//www.youtube.com/watch?v=NElJ5t_Wd48' );     
            echo '</a></p> </td></tr>';
            }, 10, 3 );
            add_action('admin_enqueue_scripts', 'aiomatic_admin_enqueue_all');
            add_filter("plugin_action_links_$plugin", 'aiomatic_add_activation_link');
        }
    }
    require_once (dirname(__FILE__) . "/aiomatic-automation.php"); 
    if(isset($_POST['aiomatic_upload_omni_files']))
    {
        if(!isset($_POST['aiomatic_nonce']) || empty($_POST['aiomatic_nonce']))
        {
            $aiomatic_result['msg'] = 'Incorrect verification token sent!';
            wp_send_json($aiomatic_result);
        }
        if (wp_verify_nonce($_POST['aiomatic_nonce'], 'aiomatic_omni') === false) 
        {
            $aiomatic_result['msg'] = 'You are not allowed to do this!';
            wp_send_json($aiomatic_result);
        }
        if(!isset($_POST['aiomatic-file-upload-location']) || empty($_POST['aiomatic-file-upload-location']))
        {
            $aiomatic_result['msg'] = 'Incorrect request sent!';
            wp_send_json($aiomatic_result);
        }
        $location = $_POST['aiomatic-file-upload-location'];
        if($location == 'remote')
        {
            if(isset($_POST['aiomatic-file-remote-rules']))
            {
                $remote_url = $_POST['aiomatic-file-remote-rules'];
                $remote_title = aiomatic_generatePostTitleFromUrl($remote_url);
                $forms_data = array(
                    'post_type' => 'aiomatic_omni_file',
                    'post_title' => $remote_title,
                    'post_content' => $remote_url,
                    'post_status' => 'publish'
                );
                remove_filter('content_save_pre', 'wp_filter_post_kses');
                remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');remove_filter('title_save_pre', 'wp_filter_kses');
                $forms_id = wp_insert_post($forms_data);
                add_filter('content_save_pre', 'wp_filter_post_kses');
                add_filter('content_filtered_save_pre', 'wp_filter_post_kses');add_filter('title_save_pre', 'wp_filter_kses');
                if(is_wp_error($forms_id))
                {
                    $aiomatic_result['msg'] = $forms_id->get_error_message();
                    wp_send_json($aiomatic_result);
                }
                elseif($forms_id === 0)
                {
                    $aiomatic_result['msg'] = 'Failed to insert file to database: ' . $title;
                    wp_send_json($aiomatic_result);
                }
                else 
                {
                    $cat_arr = array($location);
                    wp_set_object_terms($forms_id, $cat_arr, 'ai_file_type');
                    update_post_meta($forms_id, 'local_id', $remote_url);
                    $aiomatic_result['status'] = 'success';
                    $aiomatic_result['id'] = $forms_id;
                }
            }
        }
        else
        {
            $aiomatic_result = array();
            if(isset($_FILES['aiomatic-file-upload-rules']['tmp_name'])) 
            {
                global $wp_filesystem;
                if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
                    include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
                    wp_filesystem($creds);
                }
                $file = $wp_filesystem->get_contents($_FILES['aiomatic-file-upload-rules']['tmp_name']);
                if($file === false)
                {
                    $aiomatic_result['msg'] = 'Failed to download file: ' . $_FILES['aiomatic-file-upload-rules']['name'];
                    wp_send_json($aiomatic_result);
                }
                else
                {
                    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                    $filesize = strlen($file);
                    if($location == 'local')
                    {
                        $upload_dir = wp_upload_dir();
                        $aiomatic_directory = $upload_dir['basedir'] . '/aiomatic/';
                        $aiomatic_url = $upload_dir['baseurl'] . '/aiomatic/';
                        wp_mkdir_p($aiomatic_directory);
                        $new_pdf = $aiomatic_directory . $_FILES['aiomatic-file-upload-rules']['name'];
                        $new_url = $aiomatic_url . $_FILES['aiomatic-file-upload-rules']['name'];
                        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
                            include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
                            wp_filesystem($creds);
                        }
                        $ret = $wp_filesystem->put_contents($new_pdf, $file);
                        if ($ret === FALSE) 
                        {
                            $aiomatic_result['msg'] = 'Failed to upload file: ' . $_FILES['aiomatic-file-upload-rules']['name'] . ' to ' . $new_pdf;
                            wp_send_json($aiomatic_result);
                        }
                        else
                        {
                            $forms_data = array(
                                'post_type' => 'aiomatic_omni_file',
                                'post_title' => $_FILES['aiomatic-file-upload-rules']['name'],
                                'post_content' => $new_url,
                                'post_status' => 'publish'
                            );
                            remove_filter('content_save_pre', 'wp_filter_post_kses');
                            remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');remove_filter('title_save_pre', 'wp_filter_kses');
                            $forms_id = wp_insert_post($forms_data);
                            add_filter('content_save_pre', 'wp_filter_post_kses');
                            add_filter('content_filtered_save_pre', 'wp_filter_post_kses');add_filter('title_save_pre', 'wp_filter_kses');
                            if(is_wp_error($forms_id))
                            {
                                $aiomatic_result['msg'] = $forms_id->get_error_message();
                                wp_send_json($aiomatic_result);
                            }
                            elseif($forms_id === 0)
                            {
                                $aiomatic_result['msg'] = 'Failed to insert file to database: ' . $title;
                                wp_send_json($aiomatic_result);
                            }
                            else 
                            {
                                $cat_arr = array($location);
                                wp_set_object_terms($forms_id, $cat_arr, 'ai_file_type');
                                $new_pdf = str_replace('\\', '/', $new_pdf);
                                update_post_meta($forms_id, 'local_id', $new_pdf);
                                $aiomatic_result['status'] = 'success';
                                $aiomatic_result['id'] = $forms_id;
                            }
                        }
                    }
                    elseif($location == 'amazon')
                    {
                        if(!function_exists('is_plugin_active'))
                        {
                            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                        }
                        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
                        {
                            $aiomatic_result['msg'] = 'You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['bucket_name']) || trim($aiomatic_Main_Settings['bucket_name']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 bucket_name for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['s3_user']) || trim($aiomatic_Main_Settings['s3_user']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 s3_user for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['s3_pass']) || trim($aiomatic_Main_Settings['s3_pass']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 s3_pass for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['bucket_region']) || trim($aiomatic_Main_Settings['bucket_region']) == '')
                        {
                            $aiomatic_Main_Settings['bucket_region'] = 'eu-central-1';
                        }
                        try
                        {
                            $credentials = array('key' => trim($aiomatic_Main_Settings['s3_user']), 'secret' => trim($aiomatic_Main_Settings['s3_pass']));
                            $s3 = new S3Client([
                                'version' => 'latest',
                                'region'  => trim($aiomatic_Main_Settings['bucket_region']),
                                'credentials' => $credentials
                            ]);
                        }
                        catch(Exception $e)
                        {
                            $aiomatic_result['msg'] = 'Failed to initialize Amazon S3 API: ' . $e->getMessage();
                            wp_send_json($aiomatic_result);
                        }
                        if (trim($aiomatic_Main_Settings['drive_directory']) != '') {
                            $s3_remote_path = trim(trim($aiomatic_Main_Settings['drive_directory']), '/');
                            $s3_remote_path = trailingslashit($s3_remote_path);
                        }
                        else
                        {
                            $s3_remote_path = '';
                        }
                        try 
                        {
                            $obj_arr = [
                                'Bucket' => trim($aiomatic_Main_Settings['bucket_name']),
                                'Key'    => $s3_remote_path . $_FILES['aiomatic-file-upload-rules']['name'],
                                'Body'   => $file,
                                'Content-Length' => $filesize,
                                'ContentLength' => $filesize
                            ];
                            $obj_arr['ACL'] = 'public-read';
                            $awsret = $s3->putObject($obj_arr);
                            if(isset($awsret['ObjectURL']))
                            {
                                $forms_data = array(
                                    'post_type' => 'aiomatic_omni_file',
                                    'post_title' => $_FILES['aiomatic-file-upload-rules']['name'],
                                    'post_content' => $awsret['ObjectURL'],
                                    'post_status' => 'publish'
                                );
                                remove_filter('content_save_pre', 'wp_filter_post_kses');
                                remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');remove_filter('title_save_pre', 'wp_filter_kses');
                                $forms_id = wp_insert_post($forms_data);
                                add_filter('content_save_pre', 'wp_filter_post_kses');
                                add_filter('content_filtered_save_pre', 'wp_filter_post_kses');add_filter('title_save_pre', 'wp_filter_kses');
                                if(is_wp_error($forms_id))
                                {
                                    $aiomatic_result['msg'] = $forms_id->get_error_message();
                                    wp_send_json($aiomatic_result);
                                }
                                elseif($forms_id === 0)
                                {
                                    $aiomatic_result['msg'] = 'Failed to insert file to database: ' . $title;
                                    wp_send_json($aiomatic_result);
                                }
                                else 
                                {
                                    $cat_arr = array($location);
                                    wp_set_object_terms($forms_id, $cat_arr, 'ai_file_type');
                                    $uri = $awsret['ObjectURL'];
                                    $urlComponents = parse_url($uri);
                                    $key = ltrim($urlComponents['path'], '/');
                                    update_post_meta($forms_id, 'local_id', $key);
                                    $aiomatic_result['status'] = 'success';
                                    $aiomatic_result['id'] = $forms_id;
                                }
                            }
                            else
                            {
                                $aiomatic_result['msg'] = "Failed to decode Amazon S3 API response: " . print_r($awsret, true);
                                wp_send_json($aiomatic_result);
                            }
                        }
                        catch (Exception $e) 
                        {
                            $aiomatic_result['msg'] = "There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage();
                            wp_send_json($aiomatic_result);
                        } 
                    }
                    elseif($location == 'wasabi')
                    {
                        if(!function_exists('is_plugin_active'))
                        {
                            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                        }
                        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
                        {
                            $aiomatic_result['msg'] = 'You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['wasabi_bucket']) || trim($aiomatic_Main_Settings['wasabi_bucket']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 wasabi_bucket for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['wasabi_region']) || trim($aiomatic_Main_Settings['wasabi_region']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 wasabi_region for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['wasabi_user']) || trim($aiomatic_Main_Settings['wasabi_user']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 wasabi_user for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['wasabi_pass']) || trim($aiomatic_Main_Settings['wasabi_pass']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 wasabi_pass for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        try
                        {
                            $credentials = array('key' => trim($aiomatic_Main_Settings['wasabi_user']), 'secret' => trim($aiomatic_Main_Settings['wasabi_pass']));
                            $s3 = new S3Client([
                                'endpoint' => "https://" . trim($aiomatic_Main_Settings['wasabi_bucket']) . ".s3." . trim($aiomatic_Main_Settings['wasabi_region']) . ".wasabisys.com/",
                                'bucket_endpoint' => true,
                                'version' => 'latest',
                                'region'  => trim($aiomatic_Main_Settings['wasabi_region']),
                                'credentials' => $credentials
                            ]);
                        }
                        catch(Exception $e)
                        {
                            $aiomatic_result['msg'] = 'Failed to initialize Amazon S3 API: ' . $e->getMessage();
                            wp_send_json($aiomatic_result);
                        }
                        if (trim($aiomatic_Main_Settings['wasabi_directory']) != '') {
                            $s3_remote_path = trim(trim($aiomatic_Main_Settings['wasabi_directory']), '/');
                            $s3_remote_path = trailingslashit($s3_remote_path);
                        }
                        else
                        {
                            $s3_remote_path = '';
                        }
                        try 
                        {
                            $obj_arr = [
                                'Bucket' => trim($aiomatic_Main_Settings['wasabi_bucket']),
                                'Key'    => $s3_remote_path . $_FILES['aiomatic-file-upload-rules']['name'],
                                'Body'   => $file,
                                'Content-Length' => $filesize,
                                'ContentLength' => $filesize
                            ];
                            $obj_arr['ACL'] = 'public-read';
                            $awsret = $s3->putObject($obj_arr);
                            if(isset($awsret['ObjectURL']))
                            {
                                $forms_data = array(
                                    'post_type' => 'aiomatic_omni_file',
                                    'post_title' => $_FILES['aiomatic-file-upload-rules']['name'],
                                    'post_content' => $awsret['ObjectURL'],
                                    'post_status' => 'publish'
                                );
                                remove_filter('content_save_pre', 'wp_filter_post_kses');
                                remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');remove_filter('title_save_pre', 'wp_filter_kses');
                                $forms_id = wp_insert_post($forms_data);
                                add_filter('content_save_pre', 'wp_filter_post_kses');
                                add_filter('content_filtered_save_pre', 'wp_filter_post_kses');add_filter('title_save_pre', 'wp_filter_kses');
                                if(is_wp_error($forms_id))
                                {
                                    $aiomatic_result['msg'] = $forms_id->get_error_message();
                                    wp_send_json($aiomatic_result);
                                }
                                elseif($forms_id === 0)
                                {
                                    $aiomatic_result['msg'] = 'Failed to insert file to database: ' . $title;
                                    wp_send_json($aiomatic_result);
                                }
                                else 
                                {
                                    $cat_arr = array($location);
                                    wp_set_object_terms($forms_id, $cat_arr, 'ai_file_type');
                                    $uri = $awsret['ObjectURL'];
                                    $urlComponents = parse_url($uri);
                                    $key = ltrim($urlComponents['path'], '/');
                                    update_post_meta($forms_id, 'local_id', $key);
                                    $aiomatic_result['status'] = 'success';
                                    $aiomatic_result['id'] = $forms_id;
                                }
                            }
                            else
                            {
                                $aiomatic_result['msg'] = "Failed to decode Amazon S3 API response: " . print_r($awsret, true);
                                wp_send_json($aiomatic_result);
                            }
                        }
                        catch (Exception $e) 
                        {
                            $aiomatic_result['msg'] = "There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage();
                            wp_send_json($aiomatic_result);
                        } 
                    }
                    elseif($location == 'cloudflare')
                    {
                        if(!function_exists('is_plugin_active'))
                        {
                            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                        }
                        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
                        {
                            $aiomatic_result['msg'] = 'You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['cloud_bucket']) || trim($aiomatic_Main_Settings['cloud_bucket']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 cloud_bucket for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['cloud_account']) || trim($aiomatic_Main_Settings['cloud_account']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 cloud_account for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['cloud_user']) || trim($aiomatic_Main_Settings['cloud_user']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 cloud_user for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['cloud_pass']) || trim($aiomatic_Main_Settings['cloud_pass']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 cloud_pass for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        try
                        {
                            $credentials = array('key' => trim($aiomatic_Main_Settings['cloud_user']), 'secret' => trim($aiomatic_Main_Settings['cloud_pass']));
                            $s3 = new S3Client([
                                'endpoint' => "https://" . trim($aiomatic_Main_Settings['cloud_account']) . ".r2.cloudflarestorage.com",
                                'bucket_endpoint' => true,
                                'version' => 'latest',
                                'region' => 'us-east-1',
                                'credentials' => $credentials
                            ]);
                        }
                        catch(Exception $e)
                        {
                            $aiomatic_result['msg'] = 'Failed to initialize Amazon S3 API: ' . $e->getMessage();
                            wp_send_json($aiomatic_result);
                        }
                        if (trim($aiomatic_Main_Settings['cloud_directory']) != '') {
                            $s3_remote_path = trim(trim($aiomatic_Main_Settings['cloud_directory']), '/');
                            $s3_remote_path = trailingslashit($s3_remote_path);
                        }
                        else
                        {
                            $s3_remote_path = '';
                        }
                        try 
                        {
                            $obj_arr = [
                                'Bucket' => trim($aiomatic_Main_Settings['cloud_bucket']),
                                'Key'    => $s3_remote_path . $_FILES['aiomatic-file-upload-rules']['name'],
                                'Body'   => $file,
                                'Content-Length' => $filesize,
                                'ContentLength' => $filesize
                            ];
                            $obj_arr['ACL'] = 'public-read';
                            $awsret = $s3->putObject($obj_arr);
                            if(isset($awsret['ObjectURL']))
                            {
                                $forms_data = array(
                                    'post_type' => 'aiomatic_omni_file',
                                    'post_title' => $_FILES['aiomatic-file-upload-rules']['name'],
                                    'post_content' => $awsret['ObjectURL'],
                                    'post_status' => 'publish'
                                );
                                remove_filter('content_save_pre', 'wp_filter_post_kses');
                                remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');remove_filter('title_save_pre', 'wp_filter_kses');
                                $forms_id = wp_insert_post($forms_data);
                                add_filter('content_save_pre', 'wp_filter_post_kses');
                                add_filter('content_filtered_save_pre', 'wp_filter_post_kses');add_filter('title_save_pre', 'wp_filter_kses');
                                if(is_wp_error($forms_id))
                                {
                                    $aiomatic_result['msg'] = $forms_id->get_error_message();
                                    wp_send_json($aiomatic_result);
                                }
                                elseif($forms_id === 0)
                                {
                                    $aiomatic_result['msg'] = 'Failed to insert file to database: ' . $title;
                                    wp_send_json($aiomatic_result);
                                }
                                else 
                                {
                                    $cat_arr = array($location);
                                    wp_set_object_terms($forms_id, $cat_arr, 'ai_file_type');
                                    $uri = $awsret['ObjectURL'];
                                    $urlComponents = parse_url($uri);
                                    $key = ltrim($urlComponents['path'], '/');
                                    update_post_meta($forms_id, 'local_id', $key);
                                    $aiomatic_result['status'] = 'success';
                                    $aiomatic_result['id'] = $forms_id;
                                }
                            }
                            else
                            {
                                $aiomatic_result['msg'] = "Failed to decode Amazon S3 API response: " . print_r($awsret, true);
                                wp_send_json($aiomatic_result);
                            }
                        }
                        catch (Exception $e) 
                        {
                            $aiomatic_result['msg'] = "There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage();
                            wp_send_json($aiomatic_result);
                        } 
                    }
                    elseif($location == 'digital')
                    {
                        if(!function_exists('is_plugin_active'))
                        {
                            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                        }
                        if (!is_plugin_active('aiomatic-extension-amazon-s3-images/aiomatic-extension-amazon-s3-images.php')) 
                        {
                            $aiomatic_result['msg'] = 'You need enable the "Aiomatic Extension: Amazon S3 Storage" plugin for this feature to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['digital_endpoint']) || trim($aiomatic_Main_Settings['digital_endpoint']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 digital_endpoint for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['digital_user']) || trim($aiomatic_Main_Settings['digital_user']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 digital_user for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        if (!isset($aiomatic_Main_Settings['digital_pass']) || trim($aiomatic_Main_Settings['digital_pass']) == '')
                        {
                            $aiomatic_result['msg'] = 'You need to enter a Amazon S3 digital_pass for this to work!';
                            wp_send_json($aiomatic_result);
                        }
                        $bucket_name = '';
                        preg_match_all('#https:\/\/([^.]*?)\.(?:[^.]*?)\.digitaloceanspaces\.com#i', trim($aiomatic_Main_Settings['digital_endpoint']), $zmatches);
                        if(isset($zmatches[1][0]))
                        {
                            $bucket_name = $zmatches[1][0];
                        }
                        else
                        {
                            $aiomatic_result['msg'] = 'Failed to parse Digital Ocean Spaces URL: ' . trim($aiomatic_Main_Settings['digital_endpoint']);
                            wp_send_json($aiomatic_result);
                        }
                        $endpoint_plain_url = preg_replace('#https?:\/\/([^.]*?\.)([^.]*?)\.digitaloceanspaces\.com#i', 'https://$2.digitaloceanspaces.com', trim($aiomatic_Main_Settings['digital_endpoint']));
                        try
                        {
                            $credentials = array('key' => trim($aiomatic_Main_Settings['digital_user']), 'secret' => trim($aiomatic_Main_Settings['digital_pass']));
                            $s3 = new S3Client([
                                'version' => 'latest',
                                'region'  => 'us-east-1',
                                'endpoint' => $endpoint_plain_url,
                                'use_path_style_endpoint' => false,
                                'credentials' => $credentials
                            ]);
                        }
                        catch(Exception $e)
                        {
                            $aiomatic_result['msg'] = 'Failed to initialize Amazon S3 API: ' . $e->getMessage();
                            wp_send_json($aiomatic_result);
                        }
                        if (trim($aiomatic_Main_Settings['digital_directory']) != '') {
                            $s3_remote_path = trim(trim($aiomatic_Main_Settings['digital_directory']), '/');
                            $s3_remote_path = trailingslashit($s3_remote_path);
                        }
                        else
                        {
                            $s3_remote_path = '';
                        }
                        try 
                        {
                            $obj_arr = [
                                'Bucket' => trim($bucket_name),
                                'Key'    => $s3_remote_path . $_FILES['aiomatic-file-upload-rules']['name'],
                                'Body'   => $file,
                                'Content-Length' => $filesize,
                                'ContentLength' => $filesize
                            ];
                            $obj_arr['ACL'] = 'public-read';
                            $awsret = $s3->putObject($obj_arr);
                            if(isset($awsret['ObjectURL']))
                            {
                                $forms_data = array(
                                    'post_type' => 'aiomatic_omni_file',
                                    'post_title' => $_FILES['aiomatic-file-upload-rules']['name'],
                                    'post_content' => $awsret['ObjectURL'],
                                    'post_status' => 'publish'
                                );
                                remove_filter('content_save_pre', 'wp_filter_post_kses');
                                remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');remove_filter('title_save_pre', 'wp_filter_kses');
                                $forms_id = wp_insert_post($forms_data);
                                add_filter('content_save_pre', 'wp_filter_post_kses');
                                add_filter('content_filtered_save_pre', 'wp_filter_post_kses');add_filter('title_save_pre', 'wp_filter_kses');
                                if(is_wp_error($forms_id))
                                {
                                    $aiomatic_result['msg'] = $forms_id->get_error_message();
                                    wp_send_json($aiomatic_result);
                                }
                                elseif($forms_id === 0)
                                {
                                    $aiomatic_result['msg'] = 'Failed to insert file to database: ' . $title;
                                    wp_send_json($aiomatic_result);
                                }
                                else 
                                {
                                    $cat_arr = array($location);
                                    wp_set_object_terms($forms_id, $cat_arr, 'ai_file_type');
                                    $uri = $awsret['ObjectURL'];
                                    $urlComponents = parse_url($uri);
                                    $key = ltrim($urlComponents['path'], '/');
                                    update_post_meta($forms_id, 'local_id', $key);
                                    $aiomatic_result['status'] = 'success';
                                    $aiomatic_result['id'] = $forms_id;
                                }
                            }
                            else
                            {
                                $aiomatic_result['msg'] = "Failed to decode Amazon S3 API response: " . print_r($awsret, true);
                                wp_send_json($aiomatic_result);
                            }
                        }
                        catch (Exception $e) 
                        {
                            $aiomatic_result['msg'] = "There was an error uploading the file " . $image_url . " to Amazon S3: " . $e->getMessage();
                            wp_send_json($aiomatic_result);
                        } 
                    }
                    else
                    {
                        $aiomatic_result['msg'] = 'Incorrect location provided';
                        wp_send_json($aiomatic_result);
                    }
                }               
            }
            else
            {
                $aiomatic_result['msg'] = 'Incorrect function call';
                wp_send_json($aiomatic_result);
            }
        }
    }
    if(isset($_POST['aiomatic_download_forms_to_file']))
    {
        $aiomatic_result = array();
        if(!isset($_POST['aiomatic_nonce']) || empty($_POST['aiomatic_nonce']))
        {
            $aiomatic_result['msg'] = 'Incorrect verification token sent!';
            wp_send_json($aiomatic_result);
        }
        if (wp_verify_nonce($_POST['aiomatic_nonce'], 'aiomatic_forms') === false) 
        {
            $aiomatic_result['msg'] = 'You are not allowed to do this!';
            wp_send_json($aiomatic_result);
        }
        $aiomatic_form_page = 1;
        $aiomatic_forms = new WP_Query(array(
            'post_type' => 'aiomatic_forms',
            'order' => 'DESC',
            'orderby' => 'date',
            'posts_per_page' => 50,
            'paged' => $aiomatic_form_page
        ));
        $forms = array();
        while($aiomatic_forms->have_posts())
        {
            foreach ($aiomatic_forms->posts as $aiomatic_form){
                $my_form = array();
                $prompt = get_post_meta($aiomatic_form->ID, 'prompt', true);
                $model = get_post_meta($aiomatic_form->ID, 'model', true);
                $assistant_id = get_post_meta($aiomatic_form->ID, 'assistant_id', true);
                $header = get_post_meta($aiomatic_form->ID, 'header', true);
                $editor = get_post_meta($aiomatic_form->ID, 'editor', true);
                $advanced = get_post_meta($aiomatic_form->ID, 'advanced', true);
                $submit = get_post_meta($aiomatic_form->ID, 'submit', true);
                $max = get_post_meta($aiomatic_form->ID, 'max', true);
                $temperature = get_post_meta($aiomatic_form->ID, 'temperature', true);
                $topp = get_post_meta($aiomatic_form->ID, 'topp', true);
                $presence = get_post_meta($aiomatic_form->ID, 'presence', true);
                $frequency = get_post_meta($aiomatic_form->ID, 'frequency', true);
                $response = get_post_meta($aiomatic_form->ID, 'response', true);
                $streaming_enabled = get_post_meta($aiomatic_form->ID, 'streaming_enabled', true);
                $type = get_post_meta($aiomatic_form->ID, 'type', true);
                $aiomaticfields = get_post_meta($aiomatic_form->ID, '_aiomaticfields', true);
                if(!is_array($aiomaticfields))
                {
                    $aiomaticfields = array();
                }
                $my_form['title'] = $aiomatic_form->post_title;
                $my_form['description'] = $aiomatic_form->post_content;
                $my_form['prompt'] = $prompt;
                $my_form['model'] = $model;
                $my_form['assistant_id'] = $assistant_id;
                $my_form['header'] = $header;
                $my_form['editor'] = $editor;
                $my_form['advanced'] = $advanced;
                $my_form['submit'] = $submit;
                $my_form['max'] = $max;
                $my_form['temperature'] = $temperature;
                $my_form['topp'] = $topp;
                $my_form['presence'] = $presence;
                $my_form['frequency'] = $frequency;
                $my_form['streaming_enabled'] = $streaming_enabled;
                $my_form['response'] = $response;
                $my_form['type'] = $type;
                $my_form['aiomaticfields'] = $aiomaticfields;
                $forms[] = $my_form;
            }
            $aiomatic_form_page++;
            $aiomatic_forms = new WP_Query(array(
                'post_type' => 'aiomatic_forms',
                'order' => 'DESC',
                'orderby' => 'date',
                'posts_per_page' => 50,
                'paged' => $aiomatic_form_page
            ));
        }
        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=aiomatic_forms.json");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo json_encode($forms);
        exit();
    }
    if(isset($_POST['aiomatic_download_personas_to_file']))
    {
        $aiomatic_result = array();
        if(!isset($_POST['aiomatic_nonce']) || empty($_POST['aiomatic_nonce']))
        {
            $aiomatic_result['msg'] = 'Incorrect verification token sent!';
            wp_send_json($aiomatic_result);
        }
        if (wp_verify_nonce($_POST['aiomatic_nonce'], 'aiomatic_personas') === false) 
        {
            $aiomatic_result['msg'] = 'You are not allowed to do this!';
            wp_send_json($aiomatic_result);
        }
        $aiomatic_persona_page = 1;
        $aiomatic_personas = new WP_Query(array(
            'post_type' => 'aiomatic_personas',
            'order' => 'DESC',
            'orderby' => 'date',
            'posts_per_page' => 50,
            'paged' => $aiomatic_persona_page
        ));
        $personas = array();
        while($aiomatic_personas->have_posts())
        {
            foreach ($aiomatic_personas->posts as $aiomatic_persona)
            {
                $message = get_post_meta($aiomatic_persona->ID, '_persona_first_message', true);
                $my_persona = array();
                $my_persona['name'] = $aiomatic_persona->post_title;
                $my_persona['role'] = $aiomatic_persona->post_excerpt;
                $my_persona['prompt'] = $aiomatic_persona->post_content;
                $my_persona['avatar'] = get_post_thumbnail_id($aiomatic_persona->ID);
                $my_persona['message'] = $message;
                $personas[] = $my_persona;
            }
            $aiomatic_persona_page++;
            $aiomatic_personas = new WP_Query(array(
                'post_type' => 'aiomatic_personas',
                'order' => 'DESC',
                'orderby' => 'date',
                'posts_per_page' => 50,
                'paged' => $aiomatic_persona_page
            ));
        }
        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=aiomatic_personas.json");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo json_encode($personas);
        exit();
    }
    if(isset($_POST['aiomatic_download_assistants_to_file']))
    {
        $aiomatic_result = array();
        if(!isset($_POST['aiomatic_nonce']) || empty($_POST['aiomatic_nonce']))
        {
            $aiomatic_result['msg'] = 'Incorrect verification token sent!';
            wp_send_json($aiomatic_result);
        }
        if (wp_verify_nonce($_POST['aiomatic_nonce'], 'aiomatic_assistants') === false) 
        {
            $aiomatic_result['msg'] = 'You are not allowed to do this!';
            wp_send_json($aiomatic_result);
        }
        $aiomatic_assistant_page = 1;
        $aiomatic_assistants = new WP_Query(array(
            'post_type' => 'aiomatic_assistants',
            'order' => 'DESC',
            'orderby' => 'date',
            'posts_per_page' => 50,
            'paged' => $aiomatic_assistant_page
        ));
        $assistants = array();
        while($aiomatic_assistants->have_posts())
        {
            foreach ($aiomatic_assistants->posts as $aiomatic_assistant)
            {
                $code_interpreter = false;
                $file_search = false;
                $functions = [];
                $tools = get_post_meta($aiomatic_assistant->ID, '_assistant_tools', true);
                $ass_id = get_post_meta($aiomatic_assistant->ID, '_assistant_id', true);
                if(!empty($tools))
                {
                    foreach($tools as $tool)
                    {
                        if($tool['type'] == 'code_interpreter')
                        {
                            $code_interpreter = true;
                        }
                        elseif($tool['type'] == 'file_search')
                        {
                            $file_search = true;
                        }
                        elseif($tool['type'] == 'function')
                        {
                            $functions[] = $tool['function'];
                        }
                    }
                }
                $message = get_post_meta($aiomatic_assistant->ID, '_assistant_first_message', true);
                $assistant_model = get_post_meta($aiomatic_assistant->ID, '_assistant_model', true);
                $assistant_files = get_post_meta($aiomatic_assistant->ID, '_assistant_files', true);
                $temperature = get_post_meta($aiomatic_assistant->ID, '_assistant_temperature', true);
                $topp = get_post_meta($aiomatic_assistant->ID, '_assistant_topp', true);
                $my_assistant = array();
                $my_assistant['name'] = $aiomatic_assistant->post_title;
                $my_assistant['id'] = $ass_id;
                $my_assistant['role'] = $aiomatic_assistant->post_excerpt;
                $my_assistant['prompt'] = $aiomatic_assistant->post_content;
                $my_assistant['avatar'] = get_post_thumbnail_id($aiomatic_assistant->ID);
                $my_assistant['message'] = $message;
                $my_assistant['model'] = $assistant_model;
                $my_assistant['temperature'] = $temperature;
                $my_assistant['topp'] = $topp;
                $my_assistant['files'] = $assistant_files;
                $my_assistant['code_interpreter'] = $code_interpreter;
                $my_assistant['file_search'] = $file_search;
                $my_assistant['functions'] = $functions;
                $assistants[] = $my_assistant;
            }
            $aiomatic_assistant_page++;
            $aiomatic_assistants = new WP_Query(array(
                'post_type' => 'aiomatic_assistants',
                'order' => 'DESC',
                'orderby' => 'date',
                'posts_per_page' => 50,
                'paged' => $aiomatic_assistant_page
            ));
        }
        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=aiomatic_assistants.json");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo json_encode($assistants);
        exit();
    }
    if(isset($_POST['aiomatic_download_omni_to_file']))
    {
        $aiomatic_result = array();
        if(!isset($_POST['aiomatic_nonce']) || empty($_POST['aiomatic_nonce']))
        {
            $aiomatic_result['msg'] = 'Incorrect verification token sent!';
            wp_send_json($aiomatic_result);
        }
        if (wp_verify_nonce($_POST['aiomatic_nonce'], 'aiomatic_omni') === false) 
        {
            $aiomatic_result['msg'] = 'You are not allowed to do this!';
            wp_send_json($aiomatic_result);
        }
        $aiomatic_omni_page = 1;
        $aiomatic_omni = new WP_Query(array(
            'post_type' => 'aiomatic_omni_temp',
            'order' => 'DESC',
            'orderby' => 'date',
            'posts_per_page' => 50,
            'paged' => $aiomatic_omni_page
        ));
        $templates = array();
        while($aiomatic_omni->have_posts())
        {
            foreach ($aiomatic_omni->posts as $aiomatic_assistant)
            {
                $my_temp['name'] = $aiomatic_assistant->post_title;
                $my_temp['id'] = $aiomatic_assistant->ID;
                $json_back = get_post_meta($aiomatic_assistant->ID, 'aiomatic_json', true);
                if(!empty($json_back))
                {
                    $aiomatic_assistant->post_content = $json_back;
                }
                $jsonme = json_decode($aiomatic_assistant->post_content);
                if($jsonme === null)
                {
                    $jsonme = $aiomatic_assistant->post_content;
                }
                $my_temp['json'] = $jsonme;
                $save_term = array();
                $terms = wp_get_object_terms( $aiomatic_assistant->ID, 'ai_template_categories' );
                if(!is_wp_error($terms))
                {
                    foreach($terms as  $tm)
                    {
                        $save_term[] = $tm->slug;
                    }
                }
                $my_temp['category'] = $save_term;
                $templates[] = $my_temp;
            }
            $aiomatic_omni_page++;
            $aiomatic_omni = new WP_Query(array(
                'post_type' => 'aiomatic_omni_temp',
                'order' => 'DESC',
                'orderby' => 'date',
                'posts_per_page' => 50,
                'paged' => $aiomatic_omni_page
            ));
        }
        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=aiomatic_omniblock_templates.json");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo json_encode($templates);
        exit();
    }
    if(isset($_POST['aiomatic_download_omni_file']))
    {
        $aiomatic_result = array();
        if(!isset($_POST['aiomatic_nonce']) || empty($_POST['aiomatic_nonce']))
        {
            $aiomatic_result['msg'] = 'Incorrect verification token sent!';
            wp_send_json($aiomatic_result);
        }
        if (wp_verify_nonce($_POST['aiomatic_nonce'], 'aiomatic_omni') === false) 
        {
            $aiomatic_result['msg'] = 'You are not allowed to do this!';
            wp_send_json($aiomatic_result);
        }
        if(!isset($_POST['aiomatic_fid']) || empty($_POST['aiomatic_fid']))
        {
            $aiomatic_result['msg'] = 'Incorrect request sent';
            wp_send_json($aiomatic_result);
        }
        $aiomatic_f = get_post($_POST['aiomatic_fid']);
        if($aiomatic_f === null)
        {
            $aiomatic_result['msg'] = 'Nothing to download';
            wp_send_json($aiomatic_result);
        }
        $file_type = '';
        $terms = wp_get_object_terms( $_POST['aiomatic_fid'], 'ai_file_type' );
        if(!is_wp_error($terms))
        {
            foreach($terms as  $tm)
            {
                $file_type = $tm->slug;
                break;
            }
        }
        global $wp_filesystem;
        if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
            include_once(ABSPATH . 'wp-admin/includes/file.php');$creds = request_filesystem_credentials( site_url() );
            wp_filesystem($creds);
        }
        
        $f_cont = '';
        if($file_type == 'local')
        {
            $local_id = get_post_meta($_POST['aiomatic_fid'], 'local_id', true);
            if(empty($local_id))
            {
                $aiomatic_result['msg'] = 'Local file path not found';
                wp_send_json($aiomatic_result);
            }
            $f_cont = $wp_filesystem->get_contents($local_id);
            if($f_cont === false)
            {
                $aiomatic_result['msg'] = 'Failed to read file';
                wp_send_json($aiomatic_result);
            }
        }
        else
        {
            $ulrdl = $aiomatic_f->post_content;
            $f_cont = aiomatic_scrape_page(trim($ulrdl), '0', 'raw', '');
            if($f_cont === false)
            {
                $aiomatic_result['msg'] = 'Failed to download remote file';
                wp_send_json($aiomatic_result);
            }
        }
        header("Content-type: application/x-msdownload");
        header("Content-Disposition: attachment; filename=" . $aiomatic_f->post_title);
        header("Pragma: no-cache");
        header("Expires: 0");
        echo $f_cont;
        exit();
    }
    require_once (dirname(__FILE__) . "/res/aiomatic-finetune.php"); 
    require_once (dirname(__FILE__) . "/res/image-seo/aiomatic-image-seo.php"); 
    aiomatic_cron_schedule();
    if(isset($_GET['aiomatic_page']))
    {
        $curent_page = $_GET["aiomatic_page"];
    }
    else
    {
        $curent_page = '';
    }
    $last_url = (aiomatic_isSecure() ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    if(stristr($last_url, 'aiomatic_items_panel') !== false)
    {
        $all_rules = get_option('aiomatic_rules_list', array());
    }
    elseif(stristr($last_url, 'aiomatic_youtube_panel') !== false)
    {
        $all_rules = get_option('aiomatic_youtube_list', array());
    }
    elseif(stristr($last_url, 'aiomatic_amazon_panel') !== false)
    {
        $all_rules = get_option('aiomatic_amazon_list', array());
    }
    elseif(stristr($last_url, 'aiomatic_review_panel') !== false)
    {
        $all_rules = get_option('aiomatic_review_list', array());
    }
    elseif(stristr($last_url, 'aiomatic_csv_panel') !== false)
    {
        $all_rules = get_option('aiomatic_csv_list', array());
    }
    elseif(stristr($last_url, 'aiomatic_omniblocks') !== false)
    {
        $all_rules = get_option('aiomatic_omni_list', array());
    }
    elseif(stristr($last_url, 'aiomatic_listicle_panel') !== false)
    {
        $all_rules = get_option('aiomatic_listicle_list', array());
    }
    else
    {
        $all_rules = array();
    }
    if($all_rules === false)
    {
        $all_rules = array();
    }
    $rules_count = count($all_rules);
    $rules_per_page = get_option('aiomatic_posts_per_page', 12);
    $max_pages = ceil($rules_count/$rules_per_page);
    if($max_pages == 0)
    {
        $max_pages = 1;
    }
    if((stristr($last_url, 'aiomatic_items_panel') !== false || stristr($last_url, 'aiomatic_listicle_panel') !== false || stristr($last_url, 'aiomatic_youtube_panel') !== false || stristr($last_url, 'aiomatic_amazon_panel') !== false || stristr($last_url, 'aiomatic_review_panel') !== false || stristr($last_url, 'aiomatic_csv_panel') !== false || stristr($last_url, 'aiomatic_omniblocks') !== false)
    && (!is_numeric($curent_page) || $curent_page > $max_pages || $curent_page <= 0))
    {
        if(stristr($last_url, 'aiomatic_page=') === false)
        {
            if(stristr($last_url, '?') === false)
            {
                $last_url .= '?aiomatic_page=' . $max_pages;
            }
            else
            {
                $last_url .= '&aiomatic_page=' . $max_pages;
            }
        }
        else
        {
            if(isset($_GET['aiomatic_page']))
            {
                $curent_page = $_GET["aiomatic_page"];
            }
            else
            {
                $curent_page = '';
            }
            if(is_numeric($curent_page))
            {
                $last_url = str_replace('aiomatic_page=' . $curent_page, 'aiomatic_page=' . $max_pages, $last_url);
            }
            else
            {
                if(stristr($last_url, '?') === false)
                {
                    $last_url .= '?aiomatic_page=' . $max_pages;
                }
                else
                {
                    $last_url .= '&aiomatic_page=' . $max_pages;
                }
            }
        }
        aiomatic_redirect($last_url);
    }
    register_setting('aiomatic_option_group', 'aiomatic_Main_Settings');
    register_setting('aiomatic_option_group2', 'aiomatic_Spinner_Settings');
    register_setting('aiomatic_option_group3', 'aiomatic_Limit_Settings');
    register_setting('aiomatic_option_group4', 'aiomatic_Chatbot_Settings');
    register_setting('aiomatic_option_group5', 'aiomatic_Limit_Rules');
    if (is_multisite()) {
        if (!get_option('aiomatic_Main_Settings')) {
            aiomatic_activation_callback(TRUE);
        }
    }
}

add_action('wp_enqueue_scripts', 'aiomatic_wp_load_files');
add_action('admin_enqueue_scripts', 'aiomatic_wp_load_files');
add_action('admin_enqueue_scripts', 'aiomatic_add_admin_scripts', 10, 1);
function aiomatic_wp_load_files()
{
    $name = md5(get_bloginfo());
    $reg_css_code = '.autox-thickbox.button{margin: 0 5px;}.automaticx-video-container{position:relative;padding-bottom:56.25%;height:0;overflow:hidden}.automaticx-video-container embed,.automaticx-video-container amp-youtube,.automaticx-video-container iframe,.automaticx-video-container object{position:absolute;top:0;left:0;width:100%;height:100%}.automaticx-dual-ring{width:10px;aspect-ratio:1;border-radius:50%;border:6px solid;border-color:#000 #0000;animation:1s infinite automaticxs1}@keyframes automaticxs1{to{transform:rotate(.5turn)}}#openai-chat-response{padding-top:5px}.openchat-dots-bars-2{width:28px;height:28px;--c:linear-gradient(currentColor 0 0);--r1:radial-gradient(farthest-side at bottom,currentColor 93%,#0000);--r2:radial-gradient(farthest-side at top   ,currentColor 93%,#0000);background:var(--c),var(--r1),var(--r2),var(--c),var(--r1),var(--r2),var(--c),var(--r1),var(--r2);background-repeat:no-repeat;animation:1s infinite alternate automaticxdb2}@keyframes automaticxdb2{0%,25%{background-size:8px 0,8px 4px,8px 4px,8px 0,8px 4px,8px 4px,8px 0,8px 4px,8px 4px;background-position:0 50%,0 calc(50% - 2px),0 calc(50% + 2px),50% 50%,50% calc(50% - 2px),50% calc(50% + 2px),100% 50%,100% calc(50% - 2px),100% calc(50% + 2px)}50%{background-size:8px 100%,8px 4px,8px 4px,8px 0,8px 4px,8px 4px,8px 0,8px 4px,8px 4px;background-position:0 50%,0 calc(0% - 2px),0 calc(100% + 2px),50% 50%,50% calc(50% - 2px),50% calc(50% + 2px),100% 50%,100% calc(50% - 2px),100% calc(50% + 2px)}75%{background-size:8px 100%,8px 4px,8px 4px,8px 100%,8px 4px,8px 4px,8px 0,8px 4px,8px 4px;background-position:0 50%,0 calc(0% - 2px),0 calc(100% + 2px),50% 50%,50% calc(0% - 2px),50% calc(100% + 2px),100% 50%,100% calc(50% - 2px),100% calc(50% + 2px)}100%,95%{background-size:8px 100%,8px 4px,8px 4px,8px 100%,8px 4px,8px 4px,8px 100%,8px 4px,8px 4px;background-position:0 50%,0 calc(0% - 2px),0 calc(100% + 2px),50% 50%,50% calc(0% - 2px),50% calc(100% + 2px),100% 50%,100% calc(0% - 2px),100% calc(100% + 2px)}}';
    wp_register_style( $name . '-front-css', false, false, AIOMATIC_MAJOR_VERSION );
    wp_enqueue_style( $name . '-front-css' );
    wp_add_inline_style( $name . '-front-css', $reg_css_code );
}
function aiomatic_admin_load_files()
{
    $name = md5(get_bloginfo());
    wp_register_style($name . '-browser-style', plugins_url('styles/aiomatic-browser.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-browser-style');
    wp_register_style($name . '-modern-style', plugins_url('styles/aiomatic-modern.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-modern-style');
    wp_register_style($name . '-custom-style', plugins_url('styles/coderevolution-style.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-custom-style');
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('interface');
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('media-upload');
    wp_enqueue_script('thickbox');
    wp_enqueue_style('thickbox');
}
function aiomatic_admin_load_playground()
{
    $name = md5(get_bloginfo());
    wp_register_script($name . '-playground-script', plugins_url('scripts/playground.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-playground-script');
    wp_localize_script($name . '-playground-script', 'aiomatic_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-ajax-nonce'),
        'modelsvision' => aiomatic_get_all_vision_models()
	));
}
function aiomatic_admin_load_prompt_database()
{
    $name = md5(get_bloginfo());
    wp_enqueue_style(
        $name . '-promptdb-styles',
        plugin_dir_url(__FILE__) . 'styles/promptdb.css',
        array(),
        AIOMATIC_MAJOR_VERSION
    );
    wp_enqueue_script(
        $name . '-promptdb-scripts',
        plugin_dir_url(__FILE__) . 'scripts/promptdb.js',
        array('jquery'),
        AIOMATIC_MAJOR_VERSION,
        true
    );
}
function aiomatic_add_admin_scripts( $hook ) {

    global $post;
    if ( $hook == 'post-new.php' || $hook == 'post.php' ) 
    {
        if ( 'aiomatic_personas' === $post->post_type ) 
        {
            $name = md5(get_bloginfo());    
            wp_register_style($name . '-custom-persona-style', plugins_url('styles/aiomatic-persona.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
            wp_enqueue_style($name . '-custom-persona-style');
        }
    }
}
function aiomatic_admin_load_live_preview()
{
    $name = md5(get_bloginfo());
    wp_register_script($name . '-chat-live-preview-script', plugins_url('scripts/chat-live-preview.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-chat-live-preview-script');
    wp_localize_script($name . '-chat-live-preview-script', 'aiomatic_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-ajax-nonce'),
        'modelsvision' => aiomatic_get_all_vision_models()
	));
    wp_enqueue_media();
    wp_enqueue_script( $name . '-media-loader-js', plugins_url( 'scripts/media.js' , __FILE__ ), array('jquery'), AIOMATIC_MAJOR_VERSION );
    wp_localize_script($name . '-media-loader-js', 'aiomatic_ajax_object', array(
		'nonce' => wp_create_nonce('openai-single-nonce')
	));
    wp_register_style($name . '-custom-persona-style', plugins_url('styles/aiomatic-persona.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-custom-persona-style');
}
function aiomatic_admin_load_magic()
{
    $name = md5(get_bloginfo());
    wp_register_style($name . '-magic-style', plugins_url('styles/magic.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-magic-style');
}
function aiomatic_admin_load_stats()
{
    $name = md5(get_bloginfo());
    wp_register_script($name . '-stats-script', plugins_url('scripts/stats.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-stats-script');
    wp_localize_script($name . '-stats-script', 'aiomatic_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-ajax-nonce')
	));
    wp_register_style($name . '-limit-style', plugins_url('styles/aiomatic-limits.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-limit-style');
}
function aiomatic_admin_load_embeddings()
{
    $name = md5(get_bloginfo());
    wp_register_script($name . '-embeddings-script', plugins_url('scripts/embeddings.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-embeddings-script');
    wp_localize_script($name . '-embeddings-script', 'aiomatic_emb_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-ajax-nonce'),
		'maxfilesize' => wp_max_upload_size(),
        'plugin_dir_url' => plugin_dir_url(__FILE__)
	));
    wp_register_style($name . '-embeddings-style', plugins_url('styles/embeddings.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-embeddings-style');
}
function aiomatic_admin_load_forms()
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if (isset($aiomatic_Main_Settings['max_len']) && $aiomatic_Main_Settings['max_len'] != '')
    {
        $max_len = trim($aiomatic_Main_Settings['max_len']);
    }
    else
    {
        $max_len = '';
    }
    if (isset($aiomatic_Main_Settings['min_len']) && $aiomatic_Main_Settings['min_len'] != '')
    {
        $min_len = trim($aiomatic_Main_Settings['min_len']);
    }
    else
    {
        $min_len = '';
    }
    $name = md5(get_bloginfo());
    wp_register_script($name . '-forms-script', plugins_url('scripts/forms.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-forms-script');
    wp_localize_script($name . '-forms-script', 'aiomatic_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('openai-ajax-nonce'),
		'maxfilesize' => wp_max_upload_size(),
	));
    wp_register_style($name . '-forms-style', plugins_url('styles/forms.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-forms-style');
    //for styling for preview
    $user_id = get_current_user_id();
    wp_register_style($name . '-form-end-style', plugins_url('styles/form-end.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-form-end-style');
    $reg_css_code = '';
    if (isset($aiomatic_Main_Settings['back_color']) && $aiomatic_Main_Settings['back_color'] != '')
    {
        $reg_css_code .= '.aiomatic-prompt-item{background-color:' . trim($aiomatic_Main_Settings['back_color']) . '!important;}';
    }
    if (isset($aiomatic_Main_Settings['text_color']) && $aiomatic_Main_Settings['text_color'] != '')
    {
        $reg_css_code .= '.aiomatic-prompt-item{color:' . trim($aiomatic_Main_Settings['text_color']) . '!important;}';
    }
    if (isset($aiomatic_Main_Settings['but_color']) && $aiomatic_Main_Settings['but_color'] != '')
    {
        $reg_css_code .= '.aiomatic-generate-button{background:' . trim($aiomatic_Main_Settings['but_color']) . '!important;}.aiomatic-get-button{background:' . trim($aiomatic_Main_Settings['but_color']) . '!important;}';
    }
    if (isset($aiomatic_Main_Settings['btext_color']) && $aiomatic_Main_Settings['btext_color'] != '')
    {
        $reg_css_code .= '.aiomatic-generate-button{color:' . trim($aiomatic_Main_Settings['btext_color']) . '!important;}.aiomatic-get-button{color:' . trim($aiomatic_Main_Settings['btext_color']) . '!important;}';
    }
    if($reg_css_code != '')
    {
        wp_add_inline_style( $name . '-form-end-style', $reg_css_code );
    }
    $stream_url = esc_html(add_query_arg(array(
        'aiomatic_stream' => 'yes',
        'nonce' => wp_create_nonce('aiomatic-streaming-nonce')
    ), site_url() . '/index.php'));
    $stream_url_claude = esc_html(add_query_arg(array(
        'aiomatic_claude_stream' => 'yes',
        'nonce' => wp_create_nonce('aiomatic-streaming-nonce')
    ), site_url() . '/index.php'));
    $image_placeholder = plugins_url('images/loading.gif', __FILE__);
    wp_register_script( $name . '-forms-front-script', plugins_url('scripts/forms-front.js', __FILE__), false, AIOMATIC_MAJOR_VERSION );
    wp_enqueue_script( $name . '-forms-front-script'  );
    wp_localize_script($name . '-forms-front-script', 'aiomatic_completition_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openai-ajax-nonce'),
        'persistentnonce' => wp_create_nonce('openai-persistent-nonce'),
        'user_id' => $user_id,
        'max_len' => $max_len,
        'min_len' => $min_len,
        'stream_url' => $stream_url,
        'stream_url_claude' => $stream_url_claude,
        'claude_models' => AIOMATIC_CLAUDE_MODELS,
        'google_models' => AIOMATIC_GOOGLE_MODELS,
		'image_placeholder' => $image_placeholder,
        'huggingface_models' => aiomatic_get_huggingface_models(),
        'secretkey' => 'NDUPPe+cr2Cs2AYiN+JaoBH60cbleu6c'
    ));
    wp_enqueue_editor();
}
function aiomatic_admin_load_assistants()
{
    $name = md5(get_bloginfo());
    wp_enqueue_media();
    wp_register_script($name . '-assistants-script', plugins_url('scripts/assistants.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-assistants-script');
	wp_localize_script($name . '-assistants-script', 'aiomatic_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'maxfilesize' => wp_max_upload_size(),
        'retrival_models' => AIOMATIC_RETRIEVAL_MODELS,
		'nonce' => wp_create_nonce('openai-ajax-nonce'),
        'singlenonce' => wp_create_nonce('openai-single-nonce')
	));
    wp_register_style($name . '-assistants-style', plugins_url('styles/assistants.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-assistants-style');
}
function aiomatic_admin_load_batch()
{
    $name = md5(get_bloginfo());
    wp_register_script($name . '-batch-script', plugins_url('scripts/batch.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-batch-script');
	wp_localize_script($name . '-batch-script', 'aiomatic_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'maxfilesize' => wp_max_upload_size(),
        'retrival_models' => AIOMATIC_RETRIEVAL_MODELS,
		'nonce' => wp_create_nonce('openai-ajax-nonce'),
        'loadingstr' => esc_html__("Loading...", 'aiomatic-automatic-ai-content-writer'),
        'createdstr' => esc_html__("Batch created:", 'aiomatic-automatic-ai-content-writer'),
        'progressstr' => esc_html__("Batch in progress:", 'aiomatic-automatic-ai-content-writer'),
        'cancellingstr' => esc_html__("Batch cancelling:", 'aiomatic-automatic-ai-content-writer'),
        'cancelledstr' => esc_html__("Batch cancelled:", 'aiomatic-automatic-ai-content-writer'),
        'finalizingstr' => esc_html__("Batch finalizing:", 'aiomatic-automatic-ai-content-writer'),
        'completedstr' => esc_html__("Batch completed:", 'aiomatic-automatic-ai-content-writer'),
        'completedinstr' => esc_html__("Completion time:", 'aiomatic-automatic-ai-content-writer'),
        'failedstr' => esc_html__("Batch failed:", 'aiomatic-automatic-ai-content-writer'),
        'expiredstr' => esc_html__("Batch expired:", 'aiomatic-automatic-ai-content-writer'),
        'singlenonce' => wp_create_nonce('openai-single-nonce'),
        'moder_gpt_models_aiomatic' => AIOMATIC_BATCH_MODELS_NO_EMBEDDING,
        'moder_embedding_models_aiomatic' => AIOMATIC_EMBEDDINGS_MODELS
	));
    wp_register_style($name . '-batch-style', plugins_url('styles/batch.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-batch-style');
    wp_register_style($name . '-training-style', plugins_url('styles/training.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-training-style');
}
function aiomatic_admin_load_training()
{
    $name = md5(get_bloginfo());
    wp_register_script($name . '-training-script', plugins_url('scripts/training.js', __FILE__), array('jquery'), AIOMATIC_MAJOR_VERSION);
    wp_enqueue_script($name . '-training-script');
	wp_localize_script($name . '-training-script', 'aiomatic_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'maxfilesize' => wp_max_upload_size(),
		'nonce' => wp_create_nonce('openai-training-nonce')
	));
    wp_register_style($name . '-training-style', plugins_url('styles/training.css', __FILE__), false, AIOMATIC_MAJOR_VERSION);
    wp_enqueue_style($name . '-training-style');
}
function aiomatic_do_bulk_post()
{
    register_shutdown_function('aiomatic_clear_flag_at_shutdown', '-1', '');
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
    if (isset($aiomatic_Main_Settings['rule_timeout']) && $aiomatic_Main_Settings['rule_timeout'] != '') {
        $timeout = intval($aiomatic_Main_Settings['rule_timeout']);
    } else {
        $timeout = 36000;
    }
    ini_set('memory_limit', '-1');
    ini_set('default_socket_timeout', $timeout);
    ini_set('safe_mode', 'Off');
    ini_set('max_execution_time', $timeout);
    ini_set('ignore_user_abort', 1);
    ini_set('user_agent', aiomatic_get_random_user_agent());
    if(function_exists('ignore_user_abort'))
    {
        ignore_user_abort(true);
    }
    if(function_exists('set_time_limit'))
    {
        set_time_limit($timeout);
    }
    if (isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on') 
    {
        aiomatic_log_exec_time('Bulk Edit');
    }
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') {
        $query     = array(
        );
        if (isset($aiomatic_Spinner_Settings['author_id']) && $aiomatic_Spinner_Settings['author_id'] != '') {
            $query['author'] = $aiomatic_Spinner_Settings['author_id'];
        }
        if (isset($aiomatic_Spinner_Settings['author_name']) && $aiomatic_Spinner_Settings['author_name'] != '') {
            $query['author_name'] = $aiomatic_Spinner_Settings['author_name'];
        }
        $post_type = 'post';
        if (isset($aiomatic_Spinner_Settings['type_post']) && $aiomatic_Spinner_Settings['type_post'] != '') {
            $post_type = trim($aiomatic_Spinner_Settings['type_post']);
            $query['post_type'] = array_map('trim', explode(',', $aiomatic_Spinner_Settings['type_post']));
        }
        else
        {
            $query['post_type'] = 'post';
        }
        if (isset($aiomatic_Spinner_Settings['category_name']) && $aiomatic_Spinner_Settings['category_name'] != '') 
        {
            if($post_type === 'product')
            {
                $query['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => $aiomatic_Spinner_Settings['category_name'],
                    )
                );
            }
            else
            {
                $query['category_name'] = $aiomatic_Spinner_Settings['category_name'];
            }
        }
        if (isset($aiomatic_Spinner_Settings['tag_name']) && $aiomatic_Spinner_Settings['tag_name'] != '') 
        {
            if($post_type === 'product')
            {
                if(isset($query['tax_query']))
                {
                    $query['tax_query'][] = array(
                        'taxonomy' => 'product_tag',
                        'field'    => 'slug',
                        'terms'    => $aiomatic_Spinner_Settings['tag_name'],
                    );
                }
                else
                {
                    $query['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_tag',
                            'field'    => 'slug',
                            'terms'    => $aiomatic_Spinner_Settings['tag_name'],
                        )
                    );
                }
            }
            else
            {
                $query['tag'] = $aiomatic_Spinner_Settings['tag_name'];
            }
        }
        if (isset($aiomatic_Spinner_Settings['post_id']) && $aiomatic_Spinner_Settings['post_id'] != '') {
            $postids = $aiomatic_Spinner_Settings['post_id'];
            $postids = explode(',', $postids);
            $postids = array_map('trim', $postids);
            $query['post__in'] = $postids;
        }
        if (isset($aiomatic_Spinner_Settings['post_name']) && $aiomatic_Spinner_Settings['post_name'] != '') {
            $query['name'] = $aiomatic_Spinner_Settings['post_name'];
        }
        if (isset($aiomatic_Spinner_Settings['pagename']) && $aiomatic_Spinner_Settings['pagename'] != '') {
            $query['pagename'] = $aiomatic_Spinner_Settings['pagename'];
        }
        if (isset($aiomatic_Spinner_Settings['year']) && $aiomatic_Spinner_Settings['year'] != '') {
            $query['year'] = $aiomatic_Spinner_Settings['year'];
        }
        if (isset($aiomatic_Spinner_Settings['month']) && $aiomatic_Spinner_Settings['month'] != '') {
            $query['monthnum'] = $aiomatic_Spinner_Settings['month'];
        }
        if (isset($aiomatic_Spinner_Settings['day']) && $aiomatic_Spinner_Settings['day'] != '') {
            $query['day'] = $aiomatic_Spinner_Settings['day'];
        }
        if (isset($aiomatic_Spinner_Settings['post_parent']) && $aiomatic_Spinner_Settings['post_parent'] != '') {
            $query['post_parent'] = $aiomatic_Spinner_Settings['post_parent'];
        }
        if (isset($aiomatic_Spinner_Settings['page_id']) && $aiomatic_Spinner_Settings['page_id'] != '') {
            $query['page_id'] = $aiomatic_Spinner_Settings['page_id'];
        }
        if (isset($aiomatic_Spinner_Settings['max_nr']) && $aiomatic_Spinner_Settings['max_nr'] != '') {
            $max_nr = intval($aiomatic_Spinner_Settings['max_nr']);
        }
        else
        {
            $max_nr = 0;
        }
        if (isset($aiomatic_Spinner_Settings['delay_request']) && $aiomatic_Spinner_Settings['delay_request'] != '') {
            $delay_request = intval($aiomatic_Spinner_Settings['delay_request']);
        }
        else
        {
            $delay_request = 0;
        }
        if (isset($aiomatic_Spinner_Settings['max_posts']) && $aiomatic_Spinner_Settings['max_posts'] != '') 
        {
            if(intval($aiomatic_Spinner_Settings['max_posts']) != -1 && $max_nr > intval($aiomatic_Spinner_Settings['max_posts']))
            {
                $query['posts_per_page'] = $max_nr;
            }
            else
            {
                $query['posts_per_page'] = $aiomatic_Spinner_Settings['max_posts'];
            }
        }
        else
        {
            if($max_nr > 5)
            {
                $query['posts_per_page'] = $max_nr;
            }
        }
        if (isset($aiomatic_Spinner_Settings['search_offset']) && $aiomatic_Spinner_Settings['search_offset'] != '') {
            $query['offset'] = $aiomatic_Spinner_Settings['search_offset'];
        }
        if (isset($aiomatic_Spinner_Settings['search_query']) && $aiomatic_Spinner_Settings['search_query'] != '') {
            $query['s'] = $aiomatic_Spinner_Settings['search_query'];
        }
        if (isset($aiomatic_Spinner_Settings['meta_name']) && $aiomatic_Spinner_Settings['meta_name'] != '') {
            $query['meta_key'] = $aiomatic_Spinner_Settings['meta_name'];
        }
        if (isset($aiomatic_Spinner_Settings['meta_value']) && $aiomatic_Spinner_Settings['meta_value'] != '') {
            $query['meta_value'] = $aiomatic_Spinner_Settings['meta_value'];
        }
        if (isset($aiomatic_Spinner_Settings['order']) && $aiomatic_Spinner_Settings['order'] != 'default') {
            $query['order'] = $aiomatic_Spinner_Settings['order'];
        }
        if (isset($aiomatic_Spinner_Settings['orderby']) && $aiomatic_Spinner_Settings['orderby'] != 'default') {
            $query['orderby'] = $aiomatic_Spinner_Settings['orderby'];
        }
        if (isset($aiomatic_Spinner_Settings['featured_image']) && $aiomatic_Spinner_Settings['featured_image'] != 'any') {
            if($aiomatic_Spinner_Settings['featured_image'] == 'with')
            {
                $query['meta_query'] = array(
                    array(
                      'key' => '_thumbnail_id',
                      'compare' => 'EXISTS'
                    )
                );
            }
            elseif($aiomatic_Spinner_Settings['featured_image'] == 'without')
            {
                $query['meta_query'] = array(
                    array(
                      'key' => '_thumbnail_id',
                      'value' => '?',
                      'compare' => 'NOT EXISTS'
                    )
                );
            }
        }
        if (isset($aiomatic_Spinner_Settings['no_twice']) && $aiomatic_Spinner_Settings['no_twice'] == 'on') 
        {
            if (isset($aiomatic_Spinner_Settings['custom_name']) && trim($aiomatic_Spinner_Settings['custom_name']) != '')
            {
                $custom_name = trim($aiomatic_Spinner_Settings['custom_name']);
                $custom_name = str_replace('%%current_date%%', date("Y-m-d"), $custom_name);
            }
            else
            {
                $custom_name = 'aiomatic_published';
            }
            if(isset($query['meta_query']))
            {
                $query['meta_query'][] = array(
                    'key' => $custom_name,
                    'value' => '?',
                    'compare' => 'NOT EXISTS'
                );
            }
            else
            {
                $query['meta_query'] = array(
                    array(
                      'key' => $custom_name,
                      'value' => '?',
                      'compare' => 'NOT EXISTS'
                    )
                );
            }
        }
        if (isset($aiomatic_Spinner_Settings['post_status']) && $aiomatic_Spinner_Settings['post_status'] != '') {
            $query['post_status'] = array_map('trim', explode(',', $aiomatic_Spinner_Settings['post_status']));
        }
        else
        {
            $query['post_status'] = 'any';
        }
        $processed = 0;
        $post_list = get_posts($query);
        if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) {
            aiomatic_log_to_file('Found ' . count($post_list) . ' posts for this specific query.');
            if(count($post_list) == 0)
            {
                aiomatic_log_to_file('Query is: ' . print_r($query, true));
            }
        }
        $current = 1;
        $display = count($post_list);
        if($display > $max_nr)
        {
            $display = $max_nr;
        }
        foreach ($post_list as $post) 
        {
            if($max_nr > 0 && $processed == $max_nr)
            {
                break;
            }
            if (isset($aiomatic_Main_Settings['enable_detailed_logging'])) 
            {
                aiomatic_log_to_file('Processing post ' . $current . '/' . $display . ', ID: ' . $post->ID);
            }
            $processed++;
            aiomatic_do_post($post, true, false, false);
            $current++;
            if($delay_request > 0)
            {
                usleep($delay_request * 1000);
            }
        }
    }
    if($processed == 0)
    {
        return 'nochange';
    }
    else
    {
        return 'ok';
    }
}
function aiomatic_do_bulk_post_test()
{
    register_shutdown_function('aiomatic_clear_flag_at_shutdown', '-1', '');
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $aiomatic_Spinner_Settings = get_option('aiomatic_Spinner_Settings', false);
    if (isset($aiomatic_Main_Settings['rule_timeout']) && $aiomatic_Main_Settings['rule_timeout'] != '') {
        $timeout = intval($aiomatic_Main_Settings['rule_timeout']);
    } else {
        $timeout = 36000;
    }
    ini_set('memory_limit', '-1');
    ini_set('default_socket_timeout', $timeout);
    ini_set('safe_mode', 'Off');
    ini_set('max_execution_time', $timeout);
    ini_set('ignore_user_abort', 1);
    ini_set('user_agent', aiomatic_get_random_user_agent());
    if(function_exists('ignore_user_abort'))
    {
        ignore_user_abort(true);
    }
    if(function_exists('set_time_limit'))
    {
        set_time_limit($timeout);
    }
    $ret_list = array();
    if (isset($aiomatic_Main_Settings['aiomatic_enabled']) && $aiomatic_Main_Settings['aiomatic_enabled'] == 'on') {
        $query     = array(
        );
        if (isset($aiomatic_Spinner_Settings['author_id']) && $aiomatic_Spinner_Settings['author_id'] != '') {
            $query['author'] = $aiomatic_Spinner_Settings['author_id'];
        }
        if (isset($aiomatic_Spinner_Settings['author_name']) && $aiomatic_Spinner_Settings['author_name'] != '') {
            $query['author_name'] = $aiomatic_Spinner_Settings['author_name'];
        }
        $post_type = 'post';
        if (isset($aiomatic_Spinner_Settings['type_post']) && $aiomatic_Spinner_Settings['type_post'] != '') {
            $post_type = trim($aiomatic_Spinner_Settings['type_post']);
            $query['post_type'] = array_map('trim', explode(',', $aiomatic_Spinner_Settings['type_post']));
        }
        else
        {
            $query['post_type'] = 'post';
        }
        if (isset($aiomatic_Spinner_Settings['category_name']) && $aiomatic_Spinner_Settings['category_name'] != '') 
        {
            if($post_type === 'product')
            {
                $query['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => $aiomatic_Spinner_Settings['category_name'],
                    )
                );
            }
            else
            {
                $query['category_name'] = $aiomatic_Spinner_Settings['category_name'];
            }
        }
        if (isset($aiomatic_Spinner_Settings['tag_name']) && $aiomatic_Spinner_Settings['tag_name'] != '') 
        {
            if($post_type === 'product')
            {
                if(isset($query['tax_query']))
                {
                    $query['tax_query'][] = array(
                        'taxonomy' => 'product_tag',
                        'field'    => 'slug',
                        'terms'    => $aiomatic_Spinner_Settings['tag_name'],
                    );
                }
                else
                {
                    $query['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_tag',
                            'field'    => 'slug',
                            'terms'    => $aiomatic_Spinner_Settings['tag_name'],
                        )
                    );
                }
            }
            else
            {
                $query['tag'] = $aiomatic_Spinner_Settings['tag_name'];
            }
        }
        if (isset($aiomatic_Spinner_Settings['post_id']) && $aiomatic_Spinner_Settings['post_id'] != '') {
            $postids = $aiomatic_Spinner_Settings['post_id'];
            $postids = explode(',', $postids);
            $postids = array_map('trim', $postids);
            $query['post__in'] = $postids;
        }
        if (isset($aiomatic_Spinner_Settings['post_name']) && $aiomatic_Spinner_Settings['post_name'] != '') {
            $query['name'] = $aiomatic_Spinner_Settings['post_name'];
        }
        if (isset($aiomatic_Spinner_Settings['pagename']) && $aiomatic_Spinner_Settings['pagename'] != '') {
            $query['pagename'] = $aiomatic_Spinner_Settings['pagename'];
        }
        if (isset($aiomatic_Spinner_Settings['year']) && $aiomatic_Spinner_Settings['year'] != '') {
            $query['year'] = $aiomatic_Spinner_Settings['year'];
        }
        if (isset($aiomatic_Spinner_Settings['month']) && $aiomatic_Spinner_Settings['month'] != '') {
            $query['monthnum'] = $aiomatic_Spinner_Settings['month'];
        }
        if (isset($aiomatic_Spinner_Settings['day']) && $aiomatic_Spinner_Settings['day'] != '') {
            $query['day'] = $aiomatic_Spinner_Settings['day'];
        }
        if (isset($aiomatic_Spinner_Settings['post_parent']) && $aiomatic_Spinner_Settings['post_parent'] != '') {
            $query['post_parent'] = $aiomatic_Spinner_Settings['post_parent'];
        }
        if (isset($aiomatic_Spinner_Settings['page_id']) && $aiomatic_Spinner_Settings['page_id'] != '') {
            $query['page_id'] = $aiomatic_Spinner_Settings['page_id'];
        }
        if (isset($aiomatic_Spinner_Settings['max_nr']) && $aiomatic_Spinner_Settings['max_nr'] != '') {
            $max_nr = intval($aiomatic_Spinner_Settings['max_nr']);
        }
        else
        {
            $max_nr = 0;
        }
        if (isset($aiomatic_Spinner_Settings['max_posts']) && $aiomatic_Spinner_Settings['max_posts'] != '') 
        {
            if(intval($aiomatic_Spinner_Settings['max_posts']) != -1 && $max_nr > intval($aiomatic_Spinner_Settings['max_posts']))
            {
                $query['posts_per_page'] = $max_nr;
            }
            else
            {
                $query['posts_per_page'] = $aiomatic_Spinner_Settings['max_posts'];
            }
        }
        else
        {
            if($max_nr > 5)
            {
                $query['posts_per_page'] = $max_nr;
            }
        }
        if (isset($aiomatic_Spinner_Settings['search_offset']) && $aiomatic_Spinner_Settings['search_offset'] != '') {
            $query['offset'] = $aiomatic_Spinner_Settings['search_offset'];
        }
        if (isset($aiomatic_Spinner_Settings['search_query']) && $aiomatic_Spinner_Settings['search_query'] != '') {
            $query['s'] = $aiomatic_Spinner_Settings['search_query'];
        }
        if (isset($aiomatic_Spinner_Settings['meta_name']) && $aiomatic_Spinner_Settings['meta_name'] != '') {
            $query['meta_key'] = $aiomatic_Spinner_Settings['meta_name'];
        }
        if (isset($aiomatic_Spinner_Settings['meta_value']) && $aiomatic_Spinner_Settings['meta_value'] != '') {
            $query['meta_value'] = $aiomatic_Spinner_Settings['meta_value'];
        }
        if (isset($aiomatic_Spinner_Settings['order']) && $aiomatic_Spinner_Settings['order'] != 'default') {
            $query['order'] = $aiomatic_Spinner_Settings['order'];
        }
        if (isset($aiomatic_Spinner_Settings['orderby']) && $aiomatic_Spinner_Settings['orderby'] != 'default') {
            $query['orderby'] = $aiomatic_Spinner_Settings['orderby'];
        }
        if (isset($aiomatic_Spinner_Settings['featured_image']) && $aiomatic_Spinner_Settings['featured_image'] != 'any') {
            if($aiomatic_Spinner_Settings['featured_image'] == 'with')
            {
                $query['meta_query'] = array(
                    array(
                      'key' => '_thumbnail_id',
                      'compare' => 'EXISTS'
                    )
                );
            }
            elseif($aiomatic_Spinner_Settings['featured_image'] == 'without')
            {
                $query['meta_query'] = array(
                    array(
                      'key' => '_thumbnail_id',
                      'value' => '?',
                      'compare' => 'NOT EXISTS'
                    )
                );
            }
        }
        if (isset($aiomatic_Spinner_Settings['no_twice']) && $aiomatic_Spinner_Settings['no_twice'] == 'on') 
        {
            if (isset($aiomatic_Spinner_Settings['custom_name']) && trim($aiomatic_Spinner_Settings['custom_name']) != '')
            {
                $custom_name = trim($aiomatic_Spinner_Settings['custom_name']);
                $custom_name = str_replace('%%current_date%%', date("Y-m-d"), $custom_name);
            }
            else
            {
                $custom_name = 'aiomatic_published';
            }
            if(isset($query['meta_query']))
            {
                $query['meta_query'][] = array(
                    'key' => $custom_name,
                    'value' => '?',
                    'compare' => 'NOT EXISTS'
                );
            }
            else
            {
                $query['meta_query'] = array(
                    array(
                      'key' => $custom_name,
                      'value' => '?',
                      'compare' => 'NOT EXISTS'
                    )
                );
            }
        }
        if (isset($aiomatic_Spinner_Settings['post_status']) && $aiomatic_Spinner_Settings['post_status'] != '') {
            $query['post_status'] = array_map('trim', explode(',', $aiomatic_Spinner_Settings['post_status']));
        }
        else
        {
            $query['post_status'] = 'any';
        }
        $processed = 0;
        $post_list = get_posts($query);
        $current = 1;
        foreach ($post_list as $post) 
        {
            if($max_nr > 0 && $processed == $max_nr)
            {
                break;
            }
            $processed++;
            $ret_list[] = '<a href="' . get_edit_post_link($post->ID) . '" target="_blank">' . $post->ID . '</a>';
            $current++;
        }
    }
    if($processed == 0)
    {
        return 'nochange';
    }
    else
    {
        return implode(',', $ret_list);
    }
}

function aiomatic_google_extension_is_google_model($model)
{
    if(in_array($model, AIOMATIC_GOOGLE_MODELS))
    {
        return true;
    }
    return false;
}

function aiomatic_list_models_google(&$error)
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    $appids_google = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id_google']));
    $appids_google = array_filter($appids_google);
    $token = $appids_google[array_rand($appids_google)];
    if(empty($token))
    {
        $error = 'A Google API key is needed for this to work.';
        return false;
    }
    $ch = curl_init();
    if($ch === false)
    {
        $error = 'Error: failed to init curl in Google AI API';
        return false;
    }
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $token;      
    if (isset($aiomatic_Main_Settings['proxy_url']) && $aiomatic_Main_Settings['proxy_url'] != '' && $aiomatic_Main_Settings['proxy_url'] != 'disable' && $aiomatic_Main_Settings['proxy_url'] != 'disabled') 
    {
        $prx = explode(',', $aiomatic_Main_Settings['proxy_url']);
        $randomness = array_rand($prx);
        curl_setopt( $ch, CURLOPT_PROXY, trim($prx[$randomness]));
        if (isset($aiomatic_Main_Settings['proxy_auth']) && $aiomatic_Main_Settings['proxy_auth'] != '') 
        {
            $prx_auth = explode(',', $aiomatic_Main_Settings['proxy_auth']);
            if(isset($prx_auth[$randomness]) && trim($prx_auth[$randomness]) != '')
            {
                curl_setopt( $ch, CURLOPT_PROXYUSERPWD, trim($prx_auth[$randomness]));
            }
        }
    }
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (isset($aiomatic_Main_Settings['max_timeout']) && $aiomatic_Main_Settings['max_timeout'] != '')
    {
        $ztime = intval($aiomatic_Main_Settings['max_timeout']);
    }
    else
    {
        $ztime = 300;
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, $ztime);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    if (curl_errno($ch)) 
    {
        $error = 'Curl error: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }
    if($response === false)
    {
        $error = 'Failed to get Google API response';
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    $decodedResponse = json_decode($response, true);
    if(isset($decodedResponse[0]))
    {
        $decodedResponse = $decodedResponse[0];
    }
    if (isset($decodedResponse['error'])) 
    {
        $errorMsg = isset($decodedResponse['error']['message']) ? $decodedResponse['error']['message'] : 'Unknown error from Google AI Studio API';
        $error = 'Error: ' . $errorMsg;
        return false;
    } 
    elseif (isset($decodedResponse[0]['error'])) 
    {
        $errorMsg = isset($decodedResponse['error']['message']) ? $decodedResponse[0]['error']['message'] : 'Unknown error from Google AI Studio API';
        $error = 'Error: ' . $errorMsg;
        return false;
    } 
    elseif (empty($decodedResponse)) 
    {
        $error = 'No data found in the response ' . print_r($response, true);
        return false;
    }
    return $decodedResponse;
}
function aiomatic_filterClaudeForStream_local($handle)
{
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($handle, CURLOPT_WRITEFUNCTION, function ($curl_info, $data) 
    {
        echo $data;
        if (ob_get_length())
        {
            ob_flush();
        }
        flush();
        return strlen($data);
    });
}
function aiomatic_list_models_huggingface($token, &$error)
{
    $response = '';
    try 
    {
        require_once (dirname(__FILE__) . "/res/huggingface/api.php"); 
        
        $params = [
            'filter' => 'text-generation',
            'limit' => -1, 
            'sort' => 'downloads',
            'direction' => -1
        ];
        $env = [
            'apikey' => $token
        ];
        $sdk = new AiomaticHuggingFaceSDK($env);
        $response = $sdk->list_models($params);
    } 
    catch (Exception $e) 
    {
        $error = 'HuggingFaceb failure: ' . $e->getMessage();
        return false;
    }
    return $response;
}
if(!class_exists('Aiomatic_Claude_Streaming')) 
{
    class Aiomatic_Claude_Streaming
    {
        private static  $instance = null ;
        public static function get_instance()
        {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        public function __construct()
        {
            add_action('init', [$this, 'aiomatic_claude_stream'], 1);
        }
        public function aiomatic_claude_stream()
        {
            if(isset($_GET['aiomatic_claude_stream']) && sanitize_text_field($_GET['aiomatic_claude_stream']) == 'yes')
            {
                header('Content-type: text/event-stream');
                header('Cache-Control: no-cache');
                if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'aiomatic-streaming-nonce')) 
                {
                    $message = esc_html__('You are not allowed to do this action!', 'aiomatic-automatic-ai-content-writer');
                    $this->aiomatic_event_exit($message);
                }
                else 
                {
                    if (isset($_REQUEST['input_text']) && !empty($_REQUEST['input_text'])) 
                    {
                        if(!isset($_REQUEST['model']) || !isset($_REQUEST['temp']) || !isset($_REQUEST['top_p']) || !isset($_REQUEST['presence']) || !isset($_REQUEST['frequency']))
                        {
                            $message = esc_html__('Incomplete POST request for chat!', 'aiomatic-automatic-ai-content-writer');
                            $this->aiomatic_event_exit($message);
                        }
                        $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                        if (!isset($aiomatic_Main_Settings['app_id_claude']) || trim($aiomatic_Main_Settings['app_id_claude']) == '') 
                        {
                            $aiomatic_result = esc_html__('You need to insert a valid Anthropic Claude API Key for this to work!', 'aiomatic-automatic-ai-content-writer');
                            $this->aiomatic_event_exit($aiomatic_result);
                        }
                        if(isset($_REQUEST['user_token_cap_per_day']))
                        {
                            $user_token_cap_per_day = sanitize_text_field($_REQUEST['user_token_cap_per_day']);
                            if(!empty($user_token_cap_per_day))
                            {
                                $user_token_cap_per_day = intval($user_token_cap_per_day);
                            }
                        }
                        else
                        {
                            $user_token_cap_per_day = '';
                        }
                        if(isset($_REQUEST['user_id']))
                        {
                            $user_id = sanitize_text_field($_REQUEST['user_id']);
                        }
                        else
                        {
                            $user_id = '';
                        }
                        $input_text = stripslashes($_REQUEST['input_text']);
                        if(isset($_REQUEST['vision_file']))
                        {
                            $vision_file = stripslashes($_REQUEST['vision_file']);
                        }
                        else
                        {
                            $vision_file = '';
                        }
                        $user_question = '';
                        if(isset($_REQUEST['user_question']))
                        {
                            $user_question = stripslashes($_REQUEST['user_question']);
                        }
                        $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                        if (isset($aiomatic_Chatbot_Settings['max_input_length']) && $aiomatic_Chatbot_Settings['max_input_length'] != '' && is_numeric($aiomatic_Chatbot_Settings['max_input_length'])) 
                        {
                            if(strlen($input_text) > intval($aiomatic_Chatbot_Settings['max_input_length']))
                            {
                                $input_text = substr($input_text, 0, intval($aiomatic_Chatbot_Settings['max_input_length']));
                            }
                        }
                        $remember_string = '';
                        if(isset($_REQUEST['remember_string']))
                        {
                            $remember_string = stripslashes($_REQUEST['remember_string']);
                        }
                        if(!empty(trim($remember_string)))
                        {
                            $input_text = trim($remember_string) . PHP_EOL . $input_text;
                        }
                        $model = sanitize_text_field(stripslashes($_REQUEST['model']));
                        $temperature = sanitize_text_field($_REQUEST['temp']);
                        $top_p = sanitize_text_field($_REQUEST['top_p']);
                        $presence_penalty = sanitize_text_field($_REQUEST['presence']);
                        $frequency_penalty = sanitize_text_field($_REQUEST['frequency']);
                        $models = aiomatic_get_all_models_claude();
                        if(!in_array($model, $models))
                        {
                            $aiomatic_result = esc_html__('Invalid model provided: ', 'aiomatic-automatic-ai-content-writer') . $model;
                            $this->aiomatic_event_exit($aiomatic_result);
                        }
                        $temperature = floatval($temperature);
                        $top_p = floatval($top_p);
                        $presence_penalty = floatval($presence_penalty);
                        $frequency_penalty = floatval($frequency_penalty);
                        if($temperature < 0 || $temperature > 2)
                        {
                            $aiomatic_result = esc_html__('Invalid temperature provided: ', 'aiomatic-automatic-ai-content-writer') . $temperature;
                            $this->aiomatic_event_exit($aiomatic_result);
                        }
                        if($top_p < 0 || $top_p > 1)
                        {
                            $aiomatic_result = esc_html__('Invalid top_p provided: ', 'aiomatic-automatic-ai-content-writer') . $top_p;
                            $this->aiomatic_event_exit($aiomatic_result);
                        }
                        if($presence_penalty < -2 || $presence_penalty > 2)
                        {
                            $aiomatic_result = esc_html__('Invalid presence_penalty provided: ', 'aiomatic-automatic-ai-content-writer') . $presence_penalty;
                            $this->aiomatic_event_exit($aiomatic_result);
                        }
                        if($frequency_penalty < -2 || $frequency_penalty > 2)
                        {
                            $aiomatic_result = esc_html__('Invalid frequency_penalty provided: ', 'aiomatic-automatic-ai-content-writer') . $frequency_penalty;
                            $this->aiomatic_event_exit($aiomatic_result);
                        }
                        $used_token_count = 0;
                        if(is_numeric($user_token_cap_per_day))
                        {
                            if(empty($user_id) || $user_id == 0 || !is_numeric($user_id))
                            {
                                $aiomatic_result = sprintf( wp_kses( __( 'You are not allowed to access this form if you are not logged in. Please <a href="%s" target="_blank">log in</a> to continue.', 'aiomatic-automatic-ai-content-writer'), array(  'a' => array( 'href' => array(), 'target' => array() ) ) ), wp_login_url(get_permalink()) );
                                $this->aiomatic_event_exit($aiomatic_result);
                            }
                            $used_token_count = get_user_meta($user_id, 'aiomatic_used_chat_tokens', true);
                            if($used_token_count !== '' && $used_token_count !== false && is_numeric($used_token_count))
                            {
                                $used_token_count = intval($used_token_count);
                                if($used_token_count > $user_token_cap_per_day)
                                {
                                    $aiomatic_result = esc_html__('The daily token count for your user account was exceeded! Please try again tomorrow.', 'aiomatic-automatic-ai-content-writer');
                                    $this->aiomatic_event_exit($aiomatic_result);
                                }
                            }
                            else
                            {
                                $used_token_count = 0;
                            }
                        }
                        $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
                        $appids = array_filter($appids);
                        $token = $appids[array_rand($appids)];
                        $token = apply_filters('aiomatic_openai_api_key', $token);
                        $max_tokens = aiomatic_get_max_tokens($model);
                        $query_token_count = count(aiomatic_encode($input_text));
                        $available_tokens = aiomatic_compute_available_tokens($model, $max_tokens, $input_text, $query_token_count);
                        $thread_id = '';
                        $error = '';
                        $finish_reason = '';
                        aiomatic_generate_text($token, $model, $input_text, $available_tokens, $temperature, $top_p, $presence_penalty, $frequency_penalty, true, 'shortcodeChat',  0, $finish_reason, $error, false, false, true, $vision_file, $user_question, 'user', '', $thread_id, '', 'disabled', '', false, false);
                    }
                }
                exit;
            }
        }

        private function aiomatic_event_exit($message)
        {
            echo "event: message\n";
            echo 'data: {"error":[{"message":"' . $message . '"}]}';
            echo "\n\n";
            if (ob_get_length())
            {
                ob_end_flush();
            }
            flush();
            echo 'data: {"choices":[{"finish_reason":"stop"}]}';
            echo "\n\n";
            if (ob_get_length())
            {
                ob_end_flush();
            }
            flush();
            exit;
        }
    }
    Aiomatic_Claude_Streaming::get_instance();
}

function aiomatic_get_openrouter_models()
{
	$categories_option_value = get_option('aiomatic_openrouter_model_list', array());
	if(isset($categories_option_value['source_list']) && isset($categories_option_value['last_updated']))
	{
		if( (time() - $categories_option_value['last_updated']) < 2986400 )
		{
			return $categories_option_value;
		}
	}
	$categories = aiomatic_openrouter_retrieve_models();
	if(is_array($categories))
	{
		return $categories;
	}
	return false;
}
function aiomatic_get_huggingface_models()
{
	$huggingface_models = get_option('aiomatic_huggingface_models', array());
    $hf_arr = array();
    foreach($huggingface_models as $model => $details)
    {
        if(!in_array($model, $hf_arr))
        {
            $hf_arr[] = $model;
        }
    }
	return $hf_arr;
}
function aiomatic_openrouter_retrieve_models() 
{
    $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
    if(isset($aiomatic_Main_Settings['app_id_openrouter']) && !empty(trim($aiomatic_Main_Settings['app_id_openrouter'])))
    {
        $url = 'https://openrouter.ai/api/v1/models';
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) 
        {
            throw new Exception( 'AI Engine: ' . $response->get_error_message() );
        }
        $body = json_decode( $response['body'], true );
        if ( $body === null ) 
        {
            throw new Exception( 'Failed to decode response: ' . $response['body'] );
        }
        $models = array();
        foreach ( $body['data'] as $model ) 
        {
            $family = "n/a";
            $maxCompletionTokens = 4096;
            $maxContextualTokens = 8096;
            $priceIn = 0;
            $priceOut = 0;
            $family = explode( '/', $model['id'] )[0];
            if ( isset( $model['top_provider']['max_completion_tokens'] ) ) 
            {
                $maxCompletionTokens = (int)$model['top_provider']['max_completion_tokens'];
            }
            if ( isset( $model['context_length'] ) ) 
            {
                $maxContextualTokens = (int)$model['context_length'];
            }
            if ( isset( $model['pricing']['prompt'] ) && $model['pricing']['prompt'] > 0 ) 
            {
                $priceIn = floatval( $model['pricing']['prompt'] ) * 1000;
                $priceIn = aiomatic_truncate_float( $priceIn );
            }
            if ( isset( $model['pricing']['completion'] ) && $model['pricing']['completion'] > 0 ) 
            {
                $priceOut = floatval( $model['pricing']['completion'] ) * 1000;
                $priceOut = aiomatic_truncate_float( $priceOut );
            }

            $tags = [ 'ai' ];
            if ( preg_match( '/\((beta|alpha|preview)\)/i', $model['name'], $matches ) ) 
            {
                $tags[] = 'preview';
                $model['name'] = preg_replace( '/\((beta|alpha|preview)\)/i', '', $model['name'] );
            }
            if ( preg_match( '/vision/i', $model['name'], $matches ) ) 
            {
                $tags[] = 'vision';
            }
            $models[] = array(
                'model' => $model['id'],
                'name' => trim( $model['name'] ),
                'family' => $family,
                'mode' => 'chat',
                'price' => array(
                    'in' => $priceIn,
                    'out' => $priceOut,
                ),
                'type' => 'token',
                'unit' => 1 / 1000,
                'maxCompletionTokens' => $maxCompletionTokens,
                'maxContextualTokens' => $maxContextualTokens,
                'tags' => $tags
            );
        }
        $ai_ret_models = array(
            'source_list' => $models,
            'last_updated' => time()
        );
        if(count($models) > 0)
        {
            aiomatic_update_option('aiomatic_openrouter_model_list', $ai_ret_models, false);
        }
        return $ai_ret_models;
    }
    return false;
}
if (!isset($aiomatic_Main_Settings['no_elementor']) || $aiomatic_Main_Settings['no_elementor'] !== 'on')
{
    require_once(dirname(__FILE__) . "/aiomatic-elementor.php");
}
?>