<?php
if (!defined('ABSPATH'))
    exit;

use Elementor\Controls_Manager;
use Elementor\Controls_Stack;

if (!class_exists('Aiomatic_Content_Generator'))
{
    class Aiomatic_Content_Generator 
    {
        public function __construct() 
        {
            add_action('elementor/element/text-editor/section_editor/before_section_end', array($this, 'register_content_generation_controls'), 20, 2);
            add_action('elementor/element/text-editor/section_editor/after_section_end', array($this, 'register_openai_settings_controls'), 30, 2);
            add_action('wp_ajax_nopriv_aiomatic_generate_content', array($this, 'generate_content'));
            add_action('wp_ajax_aiomatic_generate_content', array($this, 'generate_content'));
        }

        public function register_content_generation_controls(Controls_Stack $element, $section_id) {


            $element->add_control(
                'aiomatic_content_generation',
                [
                    'label' => esc_html__('Aiomatic Content Generator', 'aiomatic-automatic-ai-content-writer'),
                    'type' => Controls_Manager::HEADING,
                    'separator' => 'before'
                ]
            );

            $element->add_control(
                'aiomatic_prompt',
                [
                    'type' => Controls_Manager::TEXTAREA,
                    'label' => esc_html__('AI Prompt', 'aiomatic-automatic-ai-content-writer'),
                    'label_block' => true,
                    'rows' => 10,
                    'description' => esc_html__('Enter the topic and any specific instructions for the content you need generated. You can also use the %%content%% shortcode to get the current content of the widget - useful for rewriting.', 'aiomatic-automatic-ai-content-writer'),
                    'dynamic' => [
                        'active' => true,
                    ],
                ]
            );

            $element->add_control(
                'aiomatic_generate',
                [
                    'type' => Controls_Manager::BUTTON,
                    'label' => '',
                    'separator' => 'before',
                    'show_label' => false,
                    'text' => esc_html__('Generate', 'aiomatic-automatic-ai-content-writer'),
                    'button_type' => 'default',
                    'event' => 'aiomatic:content:generate'

                ]
            );

        }
        public function register_openai_settings_controls(Controls_Stack $element, $section_id) 
        {

            $element->start_controls_section(
                'aiomatic_section_openai_api_settings',
                [
                    'label' => esc_html__('Aiomatic Settings', 'aiomatic-automatic-ai-content-writer'),
                    'tab' => Controls_Manager::TAB_SETTINGS,
                ]
            );
            $assistArray = aiomatic_get_all_assistants();
            if($assistArray === false)
            {
                $assistArray = array();
            }
            $newAssistArray = ['' => 'Don\'t use assistants'];
            foreach ($assistArray as $key => $value) {
                $newAssistArray[$value->ID] = $value->post_title;
            }
            $element->add_control(
                'aiomatic_assistant',
                [
                    'type' => Controls_Manager::SELECT,
                    'label' => esc_html__('AI Assistant', 'aiomatic-automatic-ai-content-writer'),
                    'description' => esc_html__('Select the AI assistant you want to use.', 'aiomatic-automatic-ai-content-writer'),
                    'options' => $newAssistArray,
                    'default' => '',
                ]
            );
            $modelArray = aiomatic_get_all_models();
            $newModelArray = [];
            foreach ($modelArray as $key => $value) {
                $newModelArray[$value] = $value;
            }
            $element->add_control(
                'aiomatic_model',
                [
                    'type' => Controls_Manager::SELECT,
                    'label' => esc_html__('AI Model', 'aiomatic-automatic-ai-content-writer'),
                    'description' => esc_html__('Select the AI model you want to use.', 'aiomatic-automatic-ai-content-writer'),
                    'options' => $newModelArray,
                    'default' => 'gpt-4o-mini',
                ]
            );
            $element->add_control(
                'aiomatic_temperature',
                [
                    'label' => esc_html__('Temperature', 'aiomatic-automatic-ai-content-writer'),
                    'description' => esc_html__('The sampling temperature to use. Higher values means the model will take more risks. Try 0.9 for more creative applications, and 0 for ones with a well-defined answer.', 'aiomatic-automatic-ai-content-writer'),
                    'type' => Controls_Manager::SLIDER,
                    'default' => [
                        'size' => 0.6,
                    ],
                    'range' => [
                        'px' => [
                            'min' => 0,
                            'step' => 0.01,
                            'max' => 2,
                        ],
                    ],
                ]
            );
            $element->add_control(
                'aiomatic_topp',
                [
                    'label' => esc_html__('AI Top_p', 'aiomatic-automatic-ai-content-writer'),
                    'description' => esc_html__('An alternative to sampling with temperature, called nucleus sampling, where the model considers the results of the tokens with top_p probability mass. So 0.1 means only the tokens comprising the top 10% probability mass are considered. We generally recommend altering this or temperature but not both.', 'aiomatic-automatic-ai-content-writer'),
                    'type' => Controls_Manager::SLIDER,
                    'default' => [
                        'size' => 0.6,
                    ],
                    'range' => [
                        'px' => [
                            'min' => 0,
                            'step' => 0.01,
                            'max' => 1,
                        ],
                    ],
                ]
            );
            $element->add_control(
                'aiomatic_presence_penalty',
                [
                    'label' => esc_html__('Presence Penalty', 'aiomatic-automatic-ai-content-writer'),
                    'description' => esc_html__('Number between -2.0 and 2.0. Default is 0. Positive values penalize new tokens based on whether they appear in the text so far, increasing the model\'s likelihood to talk about new topics.', 'aiomatic-automatic-ai-content-writer'),
                    'type' => Controls_Manager::SLIDER,
                    'default' => [
                        'size' => 0,
                    ],
                    'range' => [
                        'px' => [
                            'min' => -2.0,
                            'step' => 0.01,
                            'max' => 2.0,
                        ],
                    ],
                ]
            );
            $element->add_control(
                'aiomatic_frequency_penalty',
                [
                    'label' => esc_html__('Frequency Penalty', 'aiomatic-automatic-ai-content-writer'),
                    'description' => esc_html__('Number between -2.0 and 2.0. Default is 0. Positive values penalize new tokens based on their existing frequency in the text so far, decreasing the model\'s likelihood to repeat the same line verbatim.', 'aiomatic-automatic-ai-content-writer'),
                    'type' => Controls_Manager::SLIDER,
                    'default' => [
                        'size' => 0,
                    ],
                    'range' => [
                        'px' => [
                            'min' => -2.0,
                            'step' => 0.01,
                            'max' => 2.0,
                        ],
                    ],
                ]
            );
            $element->end_controls_section();

        }

        public function generate_content() 
        {

            check_ajax_referer('aiomatic-assistant', '_ajax_nonce-aiomatic-assistant');

            if (!current_user_can('manage_options')) 
            {
                wp_send_json_error(esc_html__('You are not allowed to do this action', 'aiomatic-automatic-ai-content-writer'));
            }

            try 
            {
                if (!isset($_POST['prompt']) || empty($_POST['prompt'])) {
                    throw new \Exception(esc_html__('Please provide the AI prompt before writing the content.', 'aiomatic-automatic-ai-content-writer'));
                }
                $prompt = 'Don\'t act as an AI assistant, write only the content you are asked. Write me content for the topic and instructions that follow: ' . $_POST['prompt'] . '\n';

                $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);

                if (!isset($_POST['model']) || empty($_POST['model'])) {
                    $model = aiomatic_get_default_model_name($aiomatic_Main_Settings);
                }
                else
                {
                    $model = $_POST['model'];
                }
                $assistant = '';
                if (isset($_POST['assistant']) && !empty($_POST['assistant'])) 
                {
                    $assistant = $_POST['assistant'];
                }
                if (!isset($_POST['temperature']) || empty($_POST['temperature'])) {
                    $temperature = 1;
                }
                else
                {
                    $temperature = floatval($_POST['temperature']);
                }
                if (!isset($_POST['topp']) || empty($_POST['topp'])) {
                    $top_p = 1;
                }
                else
                {
                    $top_p = floatval($_POST['topp']);
                }
                if (!isset($_POST['presencePenalty']) || empty($_POST['presencePenalty'])) {
                    $presence_penalty = 1;
                }
                else
                {
                    $presence_penalty = floatval($_POST['presencePenalty']);
                }
                if (!isset($_POST['frequencyPenalty']) || empty($_POST['frequencyPenalty'])) {
                    $frequency_penalty = 1;
                }
                else
                {
                    $frequency_penalty = floatval($_POST['frequencyPenalty']);
                }
                if (isset($aiomatic_Main_Settings['app_id']) && trim($aiomatic_Main_Settings['app_id']) != '') 
                {
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
                        $thread_id = '';
                        $aierror = '';
                        $finish_reason = '';
                        $generated_text = aiomatic_generate_text($token, $model, $prompt, $available_tokens, $temperature, $top_p, $presence_penalty, $frequency_penalty, false, 'ElementorWriter', 0, $finish_reason, $aierror, false, false, false, '', '', 'user', $assistant, $thread_id, '', 'disabled', '', false, false);
                        if($generated_text === false)
                        {
                            throw new \Exception(esc_html__('Failed to generate the AI reply, error: ', 'aiomatic-automatic-ai-content-writer') . $aierror);
                        }
                        else
                        {
                            $generated_text = aiomatic_sanitize_ai_result($generated_text);
                            if(empty($generated_text))
                            {
                                throw new \Exception(esc_html__('Empty AI response returned!', 'aiomatic-automatic-ai-content-writer'));
                            }
                        }
                    }
                    else
                    {
                        throw new \Exception(esc_html__('Empty AI prompt provided!', 'aiomatic-automatic-ai-content-writer'));
                    }
                }
                else
                {
                    throw new \Exception(esc_html__('You need to add an AI API key in the Aiomatic plugin\'s settings for this to work!', 'aiomatic-automatic-ai-content-writer'));
                }

                wp_send_json_success(trim($generated_text));

            } catch (\Throwable $throwable) {
                wp_send_json_error(esc_html__('Error! ', 'aiomatic-automatic-ai-content-writer') . $throwable->getMessage());
            }
        }
    }
}