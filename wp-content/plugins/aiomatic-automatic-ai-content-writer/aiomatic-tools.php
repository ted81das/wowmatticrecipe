<?php
defined('ABSPATH') or die();
function aiomatic_call_required_tool($requiredAction) 
{
    $toolOutputs = [];
    if (isset($requiredAction['submit_tool_outputs']) && isset($requiredAction['submit_tool_outputs']['tool_calls'])) 
    {
        require_once(__DIR__ . '/res/amazon-direct.php');
        foreach($requiredAction['submit_tool_outputs']['tool_calls'] as $tc)
        {
            if($tc['type'] === 'function')
            {
                if (!isset($tc['function']))
                {
                    continue;
                }
                if (isset($tc['function']['arguments'])) 
                {
                    $result = '';
                    if (isset($tc['function']['arguments']) && is_string($tc['function']['arguments'])) 
                    {
                        $targs = json_decode($tc['function']['arguments'], true);
                        if($targs !== null)
                        {
                            $tc['function']['arguments'] = $targs;
                        }
                        else
                        {
                            $strips = stripslashes($tc['function']['arguments']);
                            $targs = json_decode($strips, true);
                            if($targs !== null)
                            {
                                $tc['function']['arguments'] = $targs;
                            }
                        }
                    }
                }
                if ($tc['function']['name'] === 'aiomatic_wp_god_mode')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['called_function_name']))
                        {
                            $function_name = $tc['function']['arguments']['called_function_name'];
                            $params = $tc['function']['arguments']['parameter_array'];
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['god_whitelisted_functions']) && trim($aiomatic_Chatbot_Settings['god_whitelisted_functions']) != '')
                            {
                                $white = trim($aiomatic_Chatbot_Settings['god_whitelisted_functions']);
                                $white = preg_split('/\r\n|\r|\n/', trim($white));
                                $white = array_filter($white);
                                if(!in_array($function_name, $white))
                                {
                                    if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
                                    {
                                        aiomatic_log_to_file('Function call not allowed (assistant), not whitelisted: ' . $function_name);
                                    }
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'output' => 'You are not allowed to call this function (not on the whitelisted functions list)'
                                    ];
                                    continue;
                                }
                            }
                            if (isset($aiomatic_Chatbot_Settings['god_blacklisted_functions']) && trim($aiomatic_Chatbot_Settings['god_blacklisted_functions']) != '')
                            {
                                $black = trim($aiomatic_Chatbot_Settings['god_blacklisted_functions']);
                                $black = preg_split('/\r\n|\r|\n/', trim($black));
                                $black = array_filter($black);
                                if(in_array($function_name, $black))
                                {
                                    if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
                                    {
                                        aiomatic_log_to_file('Function call not allowed (assistant), blacklisted: ' . $function_name);
                                    }
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'output' => 'You are not allowed to call this function (on the blacklisted functions list)'
                                    ];
                                    continue;
                                }
                            }
                            if(function_exists($function_name))
                            {
                                if(!is_array($params))
                                {
                                    $jsony = json_decode($params, true);
                                    if($jsony !== null && is_array($jsony))
                                    {
                                        $params = $jsony;
                                    }
                                    else
                                    {
                                        if(empty($params))
                                        {
                                            $params = array();
                                        }
                                        else
                                        {
                                            $params = array($params);
                                        }
                                    }
                                }
                                if(isset($params['post_title']) && $function_name == 'wp_insert_post')
                                {
                                    $params = array($params);
                                }
                                $paramsAsString = aiomatic_format_function_params($params);
                                $reflection = new ReflectionFunction($function_name);
                                $requiredParamsCount = $reflection->getNumberOfRequiredParameters();
                                if(is_numeric($requiredParamsCount) && $requiredParamsCount > 0 && count($params) < $requiredParamsCount)
                                {
                                    $result = $function_name . ' function has ' . $requiredParamsCount .' required parameters, but only ' . count($params) . ' were passed to it.';
                                }
                                else
                                {
                                    if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
                                    {
                                        aiomatic_log_to_file('Calling function (assistant) ' . $function_name . '(' . $paramsAsString . ')...');
                                    }
                                    $result = call_user_func_array($function_name, $params);
                                }
                            }
                            else
                            {
                                $result = $function_name . ' function was not found on the system.';
                            }
                            if(isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] == 'on')
                            {
                                $paramsAsString = aiomatic_format_function_params($params);
                                if($result === false)
                                {
                                    aiomatic_log_to_file('Function (assistant) ' . $function_name . '(' . $paramsAsString . ') - returned false');
                                    $result = $function_name . ' returned false';
                                }
                                elseif(empty($result))
                                {
                                    aiomatic_log_to_file('Function (assistant)' . $function_name . '(' . $paramsAsString . ') - returned an empty response: ' . print_r($result, true));
                                    $result = $function_name . ' returned an empty response';
                                }
                                else
                                {
                                    aiomatic_log_to_file('Function (assistant) ' . $function_name . '(' . $paramsAsString . ') - result: ' . print_r($result, true));
                                }
                            }
                            if(is_object($result) || is_array($result))
                            {
                                $result = json_encode($result);
                            }
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => (string) $result
                            ];
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'called_function_name parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_image')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['prompt']))
                        {
                            $prompt = $tc['function']['arguments']['prompt'];
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (!isset($aiomatic_Main_Settings['app_id'])) 
                            {
                                $aiomatic_Main_Settings['app_id'] = '';
                            }
                            $appids = preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id']));
                            $appids = array_filter($appids);
                            $token = $appids[array_rand($appids)];
                            if (empty($token))
                            {
                                aiomatic_log_to_file('You need to enter an OpenAI API key for this to work!');
                                if (isset($aiomatic_Chatbot_Settings['god_mode_dalle_failed']) && trim($aiomatic_Chatbot_Settings['god_mode_dalle_failed']) != '')
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'echo' => trim($aiomatic_Chatbot_Settings['god_mode_dalle_failed']),
                                        'output' => trim($aiomatic_Chatbot_Settings['god_mode_dalle_failed'])
                                    ];
                                }
                                else
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'echo' => 'Image creation failed, please try again later.',
                                        'output' => 'Image creation failed, please try again later.'
                                    ];
                                }
                                continue;
                            }
                            if (isset($aiomatic_Chatbot_Settings['ai_image_size']) && trim($aiomatic_Chatbot_Settings['ai_image_size']) != '')
                            {
                                $image_size = trim($aiomatic_Chatbot_Settings['ai_image_size']);
                            }
                            else
                            {
                                $image_size = '512x512';
                            }
                            if (isset($aiomatic_Chatbot_Settings['ai_image_model']) && trim($aiomatic_Chatbot_Settings['ai_image_model']) != '')
                            {
                                $model = trim($aiomatic_Chatbot_Settings['ai_image_model']);
                            }
                            else
                            {
                                $model = 'dalle2';
                            }
                            $aierror = '';
                            $result = aiomatic_generate_ai_image($token, 1, $prompt, $image_size, 'chatFunctionDalleImage', false, 0, $aierror, $model);
                            if($result !== false && is_array($result))
                            {
                                foreach($result as $tmpimg)
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'echo' => '<img class="image_max_w_ai" src="' . $tmpimg . '">',
                                        'output' => $tmpimg
                                    ];
                                    break;
                                }
                            }
                            else
                            {
                                aiomatic_log_to_file('Failed to generate Dall-E image in AI chatbot: ' . $aierror);
                                if (isset($aiomatic_Chatbot_Settings['god_mode_dalle_failed']) && trim($aiomatic_Chatbot_Settings['god_mode_dalle_failed']) != '')
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'echo' => trim($aiomatic_Chatbot_Settings['god_mode_dalle_failed']),
                                        'output' => trim($aiomatic_Chatbot_Settings['god_mode_dalle_failed'])
                                    ];
                                }
                                else
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'echo' => 'Image creation failed, please try again later.',
                                        'output' => 'Image creation failed, please try again later.'
                                    ];
                                }
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'prompt parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_stable_image')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['prompt']))
                        {
                            $prompt = $tc['function']['arguments']['prompt'];
                            if (empty($prompt)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty prompt query provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['ai_image_size_stable']) && trim($aiomatic_Chatbot_Settings['ai_image_size_stable']) != '')
                            {
                                $image_size = trim($aiomatic_Chatbot_Settings['ai_image_size_stable']);
                            }
                            else
                            {
                                $image_size = '512x512';
                            }
                            $height = '512';
                            $width = '512';
                            if($image_size == '1024x1024')
                            {
                                $height = '1024';
                                $width = '1024';
                            }
                            if (isset($aiomatic_Chatbot_Settings['stable_model']) && trim($aiomatic_Chatbot_Settings['stable_model']) != '')
                            {
                                $model = trim($aiomatic_Chatbot_Settings['stable_model']);
                            }
                            else
                            {
                                $model = AIOMATIC_STABLE_DEFAULT_MODE;
                            }
                            $aierror = '';
                            $airesult = aiomatic_generate_stability_image($prompt, $height, $width, 'chatFunctionStableImage', 0, false, $aierror, false, $model);
                            if($result !== false && isset($airesult[1]))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => '<img class="image_max_w_ai" src="' . $airesult[1] . '">',
                                    'output' => $airesult[1]
                                ];
                            }
                            else
                            {
                                aiomatic_log_to_file('Failed to generate Stable Diffusion image in AI chatbot: ' . $aierror);
                                if (isset($aiomatic_Chatbot_Settings['god_mode_stable_failed']) && trim($aiomatic_Chatbot_Settings['god_mode_stable_failed']) != '')
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'echo' => trim($aiomatic_Chatbot_Settings['god_mode_stable_failed']),
                                        'output' => trim($aiomatic_Chatbot_Settings['god_mode_stable_failed'])
                                    ];
                                }
                                else
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'echo' => 'Image creation failed, please try again later.',
                                        'output' => 'Image creation failed, please try again later.'
                                    ];
                                }
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'prompt parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_midjourney_image')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['prompt']))
                        {
                            $prompt = $tc['function']['arguments']['prompt'];
                            if (empty($prompt)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty prompt query provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $height = '1024';
                            $width = '1024';
                            $aierror = '';
                            $airesult = aiomatic_generate_ai_image_midjourney($prompt, $width, $height, 'chatFunctionMidjourneyImage', false, $aierror);
                            if($result !== false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => '<img class="image_max_w_ai" src="' . $airesult . '">',
                                    'output' => $airesult
                                ];
                            }
                            else
                            {
                                aiomatic_log_to_file('Failed to generate Midjourney image in AI chatbot: ' . $aierror);
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => 'Image creation failed, please try again later.',
                                    'output' => 'Image creation failed, please try again later.'
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'prompt parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_replicate_image')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['prompt']))
                        {
                            $prompt = $tc['function']['arguments']['prompt'];
                            if (empty($prompt)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty prompt query provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $height = '1024';
                            $width = '1024';
                            $aierror = '';
                            $airesult = aiomatic_generate_replicate_image($prompt, $width, $height, 'chatFunctionReplicateImage', false, $aierror);
                            if($result !== false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => '<img class="image_max_w_ai" src="' . $airesult . '">',
                                    'output' => $airesult
                                ];
                            }
                            else
                            {
                                aiomatic_log_to_file('Failed to generate Replicate image in AI chatbot: ' . $aierror);
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => 'Image creation failed, please try again later.',
                                    'output' => 'Image creation failed, please try again later.'
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'prompt parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_stable_video')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['image_url']))
                        {
                            $image_url = $tc['function']['arguments']['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty image_url query provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['ai_video_size_stable']) && trim($aiomatic_Chatbot_Settings['ai_video_size_stable']) != '')
                            {
                                $image_size = trim($aiomatic_Chatbot_Settings['ai_video_size_stable']);
                            }
                            else
                            {
                                $image_size = '768x768';
                            }
                            $aierror = '';
                            $response_text = aiomatic_generate_stability_video($image_url, $image_size, 'chatbotStableVideo', 0, false, $aierror, false);
                            if($response_text !== false && isset($response_text[1]))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => '<div style="padding-bottom:56.25%; position:relative; display:block; width: 100%"><iframe src="' . $response_text[1] . '" width="100%" height="100%" style="position:absolute; top:0; left: 0" allowfullscreen webkitallowfullscreen frameborder="0"></iframe></div>',
                                    'output' => $airesult[1]
                                ];
                            }
                            else
                            {
                                aiomatic_log_to_file('Failed to generate Stable Diffusion video in AI chatbot: ' . $aierror);
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => 'Video creation failed, please try again later.',
                                    'output' => 'Video creation failed, please try again later.'
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'image_url parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_amazon_listing')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['query']))
                        {
                            $asin = $tc['function']['arguments']['query'];
                            if (empty($asin)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty search query provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['affiliate_id']) && trim($aiomatic_Chatbot_Settings['affiliate_id']) != '')
                            {
                                $aff_id = trim($aiomatic_Chatbot_Settings['affiliate_id']);
                            }
                            else
                            {
                                $aff_id = '';
                            }
                            if (isset($aiomatic_Chatbot_Settings['target_country']) && trim($aiomatic_Chatbot_Settings['target_country']) != '')
                            {
                                $target_country = trim($aiomatic_Chatbot_Settings['target_country']);
                            }
                            else
                            {
                                $target_country = 'com';
                            }
                            if (isset($aiomatic_Chatbot_Settings['max_products']) && trim($aiomatic_Chatbot_Settings['max_products']) != '')
                            {
                                $max_product_count = trim($aiomatic_Chatbot_Settings['max_products']);
                            }
                            else
                            {
                                $max_product_count = '3-4';
                            }
                            if (isset($aiomatic_Chatbot_Settings['sort_results']) && trim($aiomatic_Chatbot_Settings['sort_results']) != '')
                            {
                                $amaz_sort_results = trim($aiomatic_Chatbot_Settings['sort_results']);
                            }
                            else
                            {
                                $amaz_sort_results = 'none';
                            }
                            if (isset($aiomatic_Chatbot_Settings['listing_template']) && trim($aiomatic_Chatbot_Settings['listing_template']) != '')
                            {
                                $listing_template = trim($aiomatic_Chatbot_Settings['listing_template']);
                            }
                            else
                            {
                                $listing_template = '%%product_counter%%. %%product_title%% - Desciption: %%product_description%% - Link: %%product_url%% - Price: %%product_price%%';
                            }
                            
                            if(strstr($max_product_count, '-') !== false)
                            {
                                $pr_arr = explode('-', $max_product_count);
                                $minx = trim($pr_arr[0]);
                                $maxx = trim($pr_arr[1]);
                                if(is_numeric($minx) && is_numeric($maxx))
                                {
                                    $max_product_count = rand(intval($minx), intval($maxx));
                                }
                                else
                                {
                                    if(is_numeric($minx))
                                    {
                                        $max_product_count = intval($minx);
                                    }
                                    elseif(is_numeric($maxx))
                                    {
                                        $max_product_count = intval($maxx);
                                    }
                                    else
                                    {
                                        $max_product_count = 100;
                                    }
                                }
                            }
                            if(!empty($max_product_count) && is_numeric($max_product_count))
                            {
                                $max_prod = intval($max_product_count);
                            }
                            else
                            {
                                $max_prod = 100;
                            }
                            $amazresult = aiomatic_amazon_get_post($asin, trim($aff_id), $target_country, '', '', $amaz_sort_results, $max_prod, '1', array());
                            if(is_array($amazresult) && ((isset($amazresult['status']) && $amazresult['status'] == 'nothing') || count($amazresult)))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'No Amazon products found for query: ' . $asin
                                ];
                                continue;
                            }
                            if(!is_array($amazresult))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'An error occurred while search Amazon for: ' . $asin
                                ];
                                continue;
                            }
                            $final_result = '';
                            $counter = 1;
                            foreach($amazresult as $myprod)
                            {
                                $copy_template = $listing_template;
                                $copy_template = str_replace('%%product_counter%%', $counter, $copy_template);
                                $copy_template = str_replace('%%product_title%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_title)), $copy_template);
                                $copy_template = str_replace('%%product_description%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_desc)), $copy_template);
                                $copy_template = str_replace('%%product_url%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_url)), $copy_template);
                                $copy_template = str_replace('%%product_price%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_price)), $copy_template);
                                $copy_template = str_replace('%%product_list_price%%', trim(preg_replace('/\s+/', ' ', $myprod->product_list_price)), $copy_template);
                                $copy_template = str_replace('%%product_image%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_img)), $copy_template);
                                $copy_template = str_replace('%%product_cart_url%%', trim(preg_replace('/\s+/', ' ', $myprod->cart_url)), $copy_template);
                                $copy_template = str_replace('%%product_images_urls%%', trim(preg_replace('/\s+/', ' ', $myprod->product_imgs)), $copy_template);
                                $copy_template = str_replace('%%product_images%%', trim(preg_replace('/\s+/', ' ', $myprod->product_imgs_html)), $copy_template);
                                $copy_template = str_replace('%%product_reviews%%', trim(preg_replace('/\s+/', ' ', implode(PHP_EOL, $myprod->item_reviews))), $copy_template);
                                //new
                                $copy_template = str_replace('%%product_score%%', trim(preg_replace('/\s+/', ' ', $myprod->item_score)), $copy_template);
                                $copy_template = str_replace('%%product_language%%', trim(preg_replace('/\s+/', ' ', $myprod->language)), $copy_template);
                                $copy_template = str_replace('%%product_edition%%', trim(preg_replace('/\s+/', ' ', $myprod->edition)), $copy_template);
                                $copy_template = str_replace('%%product_pages_count%%', trim(preg_replace('/\s+/', ' ', $myprod->pages_count)), $copy_template);
                                $copy_template = str_replace('%%product_publication_date%%', trim(preg_replace('/\s+/', ' ', $myprod->publication_date)), $copy_template);
                                $copy_template = str_replace('%%product_contributors%%', trim(preg_replace('/\s+/', ' ', $myprod->contributors)), $copy_template);
                                $copy_template = str_replace('%%product_manufacturer%%', trim(preg_replace('/\s+/', ' ', $myprod->manufacturer)), $copy_template);
                                $copy_template = str_replace('%%product_binding%%', trim(preg_replace('/\s+/', ' ', $myprod->binding)), $copy_template);
                                $copy_template = str_replace('%%product_product_group%%', trim(preg_replace('/\s+/', ' ', $myprod->product_group)), $copy_template);
                                $copy_template = str_replace('%%product_rating%%', trim(preg_replace('/\s+/', ' ', $myprod->rating)), $copy_template);
                                $copy_template = str_replace('%%product_ean%%', trim(preg_replace('/\s+/', ' ', $myprod->eans)), $copy_template);
                                $copy_template = str_replace('%%product_part_no%%', trim(preg_replace('/\s+/', ' ', $myprod->part_no)), $copy_template);
                                $copy_template = str_replace('%%product_model%%', trim(preg_replace('/\s+/', ' ', $myprod->model)), $copy_template);
                                $copy_template = str_replace('%%product_warranty%%', trim(preg_replace('/\s+/', ' ', $myprod->warranty)), $copy_template);
                                $copy_template = str_replace('%%product_color%%', trim(preg_replace('/\s+/', ' ', $myprod->color)), $copy_template);
                                $copy_template = str_replace('%%product_is_adult%%', trim(preg_replace('/\s+/', ' ', $myprod->is_adult)), $copy_template);
                                $copy_template = str_replace('%%product_dimensions%%', trim(preg_replace('/\s+/', ' ', $myprod->dimensions)), $copy_template);
                                $copy_template = str_replace('%%product_size%%', trim(preg_replace('/\s+/', ' ', $myprod->size)), $copy_template);
                                $copy_template = str_replace('%%product_unit_count%%', trim(preg_replace('/\s+/', ' ', $myprod->unit_count)), $copy_template);
                                $counter++;
                                $final_result .= $copy_template . '\r\n'; 
                            }
                            $final_result = trim($final_result);
                            if(!empty($final_result))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => $final_result
                                ];
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Amazon did not return info for this query: ' . $asin
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'query parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_amazon_product_details')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['query']))
                        {
                            $asin = $tc['function']['arguments']['query'];
                            if (empty($asin)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty search query provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['affiliate_id']) && trim($aiomatic_Chatbot_Settings['affiliate_id']) != '')
                            {
                                $aff_id = trim($aiomatic_Chatbot_Settings['affiliate_id']);
                            }
                            else
                            {
                                $aff_id = '';
                            }
                            if (isset($aiomatic_Chatbot_Settings['target_country']) && trim($aiomatic_Chatbot_Settings['target_country']) != '')
                            {
                                $target_country = trim($aiomatic_Chatbot_Settings['target_country']);
                            }
                            else
                            {
                                $target_country = 'com';
                            }
                            $max_prod = 1;
                            $amazresult = aiomatic_amazon_get_post($asin, trim($aff_id), $target_country, '', '', '', $max_prod, '1', array());
                            if(is_array($amazresult) && ((isset($amazresult['status']) && $amazresult['status'] == 'nothing') || count($amazresult)))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'No Amazon products found for query: ' . $asin
                                ];
                                continue;
                            }
                            if(!is_array($amazresult))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'An error occurred while search Amazon for: ' . $asin
                                ];
                                continue;
                            }
                            else
                            {
                                $final_result = 'Product title: ' . $amazresult[0]->offer_title . '\n';
                                $final_result .= 'Description: ' . $amazresult[0]->offer_desc . '\n';
                                $final_result .= 'URL: ' . $amazresult[0]->offer_url . '\n';
                                $final_result .= 'Price: ' . $amazresult[0]->offer_price . '\n';
                                $final_result .= 'Listing Price: ' . $amazresult[0]->product_list_price . '\n';
                                $final_result .= 'Image: ' . $amazresult[0]->offer_img . '\n';
                                $final_result .= 'Add to cart URL: ' . $amazresult[0]->cart_url . '\n';
                                $final_result .= 'Other images: ' . $amazresult[0]->product_imgs . '\n';
                                //new
                                if(!empty($the_current_section->item_score))
                                {
                                    $final_result .= 'Score: ' . $amazresult[0]->item_score . '\n';
                                }
                                if(!empty($the_current_section->language))
                                {
                                    $final_result .= 'Language: ' . $amazresult[0]->language . '\n';
                                }
                                if(!empty($the_current_section->edition))
                                {
                                    $final_result .= 'Edition: ' . $amazresult[0]->edition . '\n';
                                }
                                if(!empty($the_current_section->pages_count))
                                {
                                    $final_result .= 'Pages Count: ' . $amazresult[0]->pages_count . '\n';
                                }
                                if(!empty($the_current_section->publication_date))
                                {
                                    $final_result .= 'Date: ' . $amazresult[0]->publication_date . '\n';
                                }
                                if(!empty($the_current_section->contributors))
                                {
                                    $final_result .= 'Contributors: ' . $amazresult[0]->contributors . '\n';
                                }
                                if(!empty($the_current_section->manufacturer))
                                {
                                    $final_result .= 'Manufacturer: ' . $amazresult[0]->manufacturer . '\n';
                                }
                                if(!empty($the_current_section->binding))
                                {
                                    $final_result .= 'Binding: ' . $amazresult[0]->binding . '\n';
                                }
                                if(!empty($the_current_section->product_group))
                                {
                                    $final_result .= 'Product Group: ' . $amazresult[0]->product_group . '\n';
                                }
                                if(!empty($the_current_section->rating))
                                {
                                    $final_result .= 'Rating: ' . $amazresult[0]->rating . '\n';
                                }
                                if(!empty($the_current_section->eans))
                                {
                                    $final_result .= 'EAN: ' . $amazresult[0]->eans . '\n';
                                }
                                if(!empty($the_current_section->part_no))
                                {
                                    $final_result .= 'Part No: ' . $amazresult[0]->part_no . '\n';
                                }
                                if(!empty($the_current_section->model))
                                {
                                    $final_result .= 'Model: ' . $amazresult[0]->model . '\n';
                                }
                                if(!empty($the_current_section->warranty))
                                {
                                    $final_result .= 'Warranty: ' . $amazresult[0]->warranty . '\n';
                                }
                                if(!empty($the_current_section->color))
                                {
                                    $final_result .= 'Color: ' . $amazresult[0]->color . '\n';
                                }
                                if(!empty($the_current_section->is_adult))
                                {
                                    $final_result .= 'Is Adult: ' . $amazresult[0]->is_adult . '\n';
                                }
                                if(!empty($the_current_section->dimensions))
                                {
                                    $final_result .= 'Dimensions: ' . $amazresult[0]->dimensions . '\n';
                                }
                                if(!empty($the_current_section->size))
                                {
                                    $final_result .= 'Size: ' . $amazresult[0]->size . '\n';
                                }
                                if(!empty($the_current_section->unit_count))
                                {
                                    $final_result .= 'Unit Count: ' . $amazresult[0]->unit_count . '\n';
                                }
                                $final_result .= 'Reviews: ' . implode(PHP_EOL, $amazresult[0]->item_reviews) . '\n';
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => $final_result
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'query parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_website_scraper')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['url']))
                        {
                            $scurl = $tc['function']['arguments']['url'];
                            if (empty($scurl)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty scrape url provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['scrape_method']) && trim($aiomatic_Chatbot_Settings['scrape_method']) != '')
                            {
                                $scrape_method = trim($aiomatic_Chatbot_Settings['scrape_method']);
                            }
                            else
                            {
                                $scrape_method = '0';
                            }
                            if (isset($aiomatic_Chatbot_Settings['max_chars']) && trim($aiomatic_Chatbot_Settings['max_chars']) != '')
                            {
                                $max_chars = trim($aiomatic_Chatbot_Settings['max_chars']);
                            }
                            else
                            {
                                $max_chars = '';
                            }
                            $scrape_selector = 'auto';
                            $scrape_string = '';
                            if (isset($aiomatic_Chatbot_Settings['strip_tags']) && trim($aiomatic_Chatbot_Settings['strip_tags']) == 'on')
                            {
                                $strip_tags = '1';
                            }
                            else
                            {
                                $strip_tags = '0';
                            }
                            $scraped_data = aiomatic_scrape_page($scurl, $scrape_method, $scrape_selector, $scrape_string);
                            if($scraped_data === false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to scrape website URL: ' . $scurl
                                ];
                                aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                            }
                            else
                            {
                                if($strip_tags == '1')
                                {
                                    $scraped_data = wp_strip_all_tags($scraped_data);
                                }
                                else
                                {
                                    $scraped_data = aiomatic_fix_relative_links($scraped_data, $scurl);
                                }
                                if(!empty($max_chars) && is_numeric($max_chars))
                                {
                                    $scraped_data = (strlen($scraped_data) > intval($max_chars)) ? substr($scraped_data, 0, intval($max_chars)) : $scraped_data;
                                }
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => $scraped_data
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'url parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_rss_parser')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['url']))
                        {
                            $scurl = $tc['function']['arguments']['url'];
                            if (empty($scurl)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty RSS feed URL provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['max_rss_items']) && trim($aiomatic_Chatbot_Settings['max_rss_items']) != '')
                            {
                                $max_rss_items = intval(trim($aiomatic_Chatbot_Settings['max_rss_items']));
                            }
                            else
                            {
                                $max_rss_items = PHP_INT_MAX;
                            }
                            if (isset($aiomatic_Chatbot_Settings['rss_template']) && trim($aiomatic_Chatbot_Settings['rss_template']) != '')
                            {
                                $rss_template = trim($aiomatic_Chatbot_Settings['rss_template']);
                            }
                            else
                            {
                                $rss_template = '[%%item_counter%%]: %%item_title%% - %%item_description%%';
                            }
                            try
                            {
                                if(!class_exists('SimplePie_Autoloader', false))
                                {
                                    require_once(dirname(__FILE__) . "/res/simplepie/autoloader.php");
                                }
                            }
                            catch(Exception $e) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to load RSS parser library'
                                ];
                                continue;
                            }
                            
                            $feed = new SimplePie();
                            $feed->set_timeout(120);
                            $feed->set_feed_url($scurl);
                            $feed->enable_cache(false);
                            $feed->strip_htmltags(false);
                            $feed->init();
                            $feed->handle_content_type();
                            if ($feed->error()) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Error in parsing RSS feed: ' . $feed->error()
                                ];
                                continue;
                            }
                            $final_result = '';
                            $items = $feed->get_items();
                            foreach($items as $itemx)
                            {
                                $post_link = trim($itemx->get_permalink());
                                if ($fauthor = $itemx->get_author()) 
                                {
                                    $user_name = $fauthor->get_name();
                                }
                                else
                                {
                                    $user_name = '';
                                }
                                $feed_cats = array();
                                foreach ($itemx->get_categories() as $xcategory)
                                {
                                    $feed_cats[] = $xcategory->get_label();
                                }
                                $post_cats = implode(',', $feed_cats);
                                $post_excerpt = $itemx->get_description();
                                $final_content = $itemx->get_content();
                                $rss_feeds[$itemx->get_title()] = array('url' => $post_link, 'author' => $user_name,  'cats' => $post_cats, 'excerpt' => $post_excerpt, 'content' => $final_content );
                            }
                            $template_copy = '';
                            $processed = 0;
                            foreach($rss_feeds as $rtitle => $this_rss)
                            {
                                if(!empty($max_rss_items) && $processed >= $max_rss_items)
                                {
                                    break;
                                }
                                $template_copy = $rss_template;
                                $template_copy = str_replace('%%item_counter%%', $processed + 1, $template_copy);
                                $template_copy = str_replace('%%item_title%%', $rtitle, $template_copy);
                                $template_copy = str_replace('%%item_content%%', $this_rss['content'], $template_copy);
                                $template_copy = str_replace('%%item_description%%', $this_rss['excerpt'], $template_copy);
                                $template_copy = str_replace('%%item_url%%', $this_rss['url'], $template_copy);
                                $template_copy = str_replace('%%item_author%%', $this_rss['author'], $template_copy);
                                $template_copy = str_replace('%%item_categories%%', $this_rss['cats'], $template_copy);
                                if(!empty($template_copy))
                                {
                                    $final_result .= $template_copy . PHP_EOL;
                                }
                                $processed++;
                            }
                            if($final_result === false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse RSS URL (no data returned): ' . $scurl
                                ];
                                aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => $final_result
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'url parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_google_parser')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['keywords']))
                        {
                            $keywords = $tc['function']['arguments']['keywords'];
                            if (empty($keywords)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty keywords parameter provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['max_google_items']) && trim($aiomatic_Chatbot_Settings['max_google_items']) != '')
                            {
                                $max_google_items = intval(trim($aiomatic_Chatbot_Settings['max_google_items']));
                            }
                            else
                            {
                                $max_google_items = PHP_INT_MAX;
                            }
                            if (isset($aiomatic_Chatbot_Settings['google_template']) && trim($aiomatic_Chatbot_Settings['google_template']) != '')
                            {
                                $google_template = trim($aiomatic_Chatbot_Settings['google_template']);
                            }
                            else
                            {
                                $google_template = '[%%item_counter%%]: %%item_title%% - %%item_snippet%%';
                            }
                            $locale = '';
                            if (isset($aiomatic_Main_Settings['internet_gl']) && $aiomatic_Main_Settings['internet_gl'] != '')
                            {
                                $locale = $aiomatic_Main_Settings['internet_gl'];
                            }
                            $internet_rez = aiomatic_internet_result($keywords, true, $locale);
                            $processed = 0;
                            $final_res = '';
                            foreach($internet_rez as $emb)
                            {
                                if(!empty($max_google_items) && $processed >= $max_google_items)
                                {
                                    break;
                                }
                                $template_copy = $google_template;
                                $template_copy = str_replace('%%item_counter%%', $processed + 1, $template_copy);
                                $template_copy = str_replace('%%item_title%%', $emb['title'], $template_copy);
                                $template_copy = str_replace('%%item_snippet%%', $emb['snippet'], $template_copy);
                                $template_copy = str_replace('%%item_url%%', $emb['link'], $template_copy);
                                $final_res .= $template_copy . PHP_EOL;
                                $processed++;
                            }
                            if($final_res === false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse Google SERP for keyword (no data returned): ' . $keywords
                                ];
                                aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => $final_res
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'keywords parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_royalty_free_image')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['keyword']))
                        {
                            $raw_img_list = array();
                            $full_result_list = array();
                            $keyword = $tc['function']['arguments']['keyword'];
                            if (empty($keyword)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty keyword parameter provided'
                                ];
                                continue;
                            }
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $temp_img_attr = '';
                            $temp_get_img = aiomatic_get_free_image($aiomatic_Main_Settings, $keyword, $temp_img_attr, 10, false, $raw_img_list, array(), $full_result_list);
                            if($temp_get_img === false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to get royalty free image for keyword: ' . $keyword
                                ];
                                aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => '<img class="image_max_w_ai" src="' . $temp_get_img . '">',
                                    'output' => $temp_get_img
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'keyword parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_lead_capture')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['email']))
                        {
                            $raw_img_list = array();
                            $full_result_list = array();
                            $name = '';
                            $phone_number = '';
                            $job_title = '';
                            $company_name = '';
                            $location = '';
                            $birth_date = '';
                            $how_you_found_us = '';
                            $website_url = '';
                            $preferred_contact_method = '';
                            $email = $tc['function']['arguments']['email'];
                            if (empty($email)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty email parameter provided'
                                ];
                                continue;
                            }
                            if(isset($tc['function']['arguments']['name']))
                            {
                                $name = $tc['function']['arguments']['name'];
                            }
                            if(isset($tc['function']['arguments']['phone_number']))
                            {
                                $phone_number = $tc['function']['arguments']['phone_number'];
                            }
                            if(isset($tc['function']['arguments']['job_title']))
                            {
                                $job_title = $tc['function']['arguments']['job_title'];
                            }
                            if(isset($tc['function']['arguments']['company_name']))
                            {
                                $company_name = $tc['function']['arguments']['company_name'];
                            }
                            if(isset($tc['function']['arguments']['location']))
                            {
                                $location = $tc['function']['arguments']['location'];
                            }
                            if(isset($tc['function']['arguments']['birth_date']))
                            {
                                $birth_date = $tc['function']['arguments']['birth_date'];
                            }
                            if(isset($tc['function']['arguments']['how_you_found_us']))
                            {
                                $how_you_found_us = $tc['function']['arguments']['how_you_found_us'];
                            }
                            if(isset($tc['function']['arguments']['website_url']))
                            {
                                $website_url = $tc['function']['arguments']['website_url'];
                            }
                            if(isset($tc['function']['arguments']['preferred_contact_method']))
                            {
                                $preferred_contact_method = $tc['function']['arguments']['preferred_contact_method'];
                            }
                            $lead_result = aiomatic_save_lead_data(
                                $email,
                                $name,
                                $phone_number,
                                $job_title,
                                $company_name,
                                $location,
                                $birth_date,
                                $how_you_found_us,
                                $website_url,
                                $preferred_contact_method
                            );
                            if($lead_result === false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Error! Failed to save the lead data.'
                                ];
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Lead data saved successfully.'
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'email parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_youtube_captions')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['url']))
                        {
                            $zurl = $tc['function']['arguments']['url'];
                            if (empty($zurl)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty url parameter provided'
                                ];
                                continue;
                            }
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $default_lang = array();
                            $returned_caption = '';
                            $za_video_page = '';
                            $ch  = curl_init();
                            if ($ch !== FALSE) 
                            {
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
                                if (isset($aiomatic_Main_Settings['max_timeout']) && $aiomatic_Main_Settings['max_timeout'] != '')
                                {
                                    $ztime = intval($aiomatic_Main_Settings['max_timeout']);
                                }
                                else
                                {
                                    $ztime = 300;
                                }
                                curl_setopt($ch, CURLOPT_TIMEOUT, $ztime);
                                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                                curl_setopt($ch, CURLOPT_REFERER, get_site_url());
                                curl_setopt($ch, CURLOPT_URL, $zurl);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                $za_video_page = curl_exec($ch);
                                if($za_video_page === false)
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $tc['id'], 
                                        'output' => 'Failed to download video URL: ' . $zurl
                                    ];
                                    continue;
                                }
                                curl_close($ch);
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to init curl in YouTube caption importing: ' . $zurl
                                ];
                                continue;
                            }
                            if($za_video_page !== false && strstr($za_video_page, 'vssId') !== false)
                            {
                                $srt_dl_link = '';
                                preg_match_all('#{"baseUrl":"([^"]+?)","name":(?:.*?),"vssId":"a?\.([^"]+?)","languageCode":"(?:[^"]+?)",(?:"kind":"asr",)?"isTranslatable":(?:[^}]+?)}#i', $za_video_page, $zmatches);
                                if(isset($zmatches[1][0]))
                                {
                                    $eng_f = false;
                                    if(in_array('en', $zmatches[2]))
                                    {
                                        $eng_f = true;
                                    }
                                    for($i = 0; $i < count($zmatches[1]); $i++)
                                    {
                                        if(count($default_lang) > 0)
                                        {
                                            if(in_array($zmatches[2][$i], $default_lang))
                                            {
                                                $srt_dl_link = str_replace('\u0026', '&', $zmatches[1][$i]);
                                                break;
                                            }
                                        }
                                        else
                                        {
                                            if(!$eng_f)
                                            {
                                                $srt_dl_link = str_replace('\u0026', '&', $zmatches[1][$i]);
                                                break;
                                            }
                                            elseif($zmatches[2][$i] == 'en')
                                            {
                                                $srt_dl_link = str_replace('\u0026', '&', $zmatches[1][$i]);
                                                break;
                                            }
                                        }
                                    }
                                    if($srt_dl_link !== '')
                                    {
                                        $ch  = curl_init();
                                        if ($ch !== FALSE) 
                                        {
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
                                            if (isset($aiomatic_Main_Settings['max_timeout']) && $aiomatic_Main_Settings['max_timeout'] != '')
                                            {
                                                $ztime = intval($aiomatic_Main_Settings['max_timeout']);
                                            }
                                            else
                                            {
                                                $ztime = 300;
                                            }
                                            curl_setopt($ch, CURLOPT_TIMEOUT, $ztime);
                                            curl_setopt($ch, CURLOPT_HTTPGET, 1);
                                            curl_setopt($ch, CURLOPT_REFERER, get_site_url());
                                            curl_setopt($ch, CURLOPT_URL, $srt_dl_link);
                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                            $xza_video_page = curl_exec($ch);
                                            if(!empty($xza_video_page))
                                            {
                                                $returned_caption = $xza_video_page;
                                                $returned_caption = preg_replace('#\s+#', ' ', $returned_caption);
                                            }
                                            curl_close($ch);
                                        }
                                        else
                                        {
                                            aiomatic_log_to_file('Failed to init curl in subtitle listing: ' . $zurl);
                                        }   
                                    }
                                }
                            }
                            if(empty($returned_caption))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to find subtitles for video URL: ' . $zurl
                                ];
                            }
                            if(stristr($returned_caption, 'transcript'))
                            {
                                $raw_returned_caption = preg_replace('#\s*?(?:start|dur)="[\d.]*?"\s*?#','', $returned_caption);
                                $raw_returned_caption = preg_replace('#\[[A-Z][a-z]+\]#','', $raw_returned_caption);
                                $returned_caption = '';
                                $capt = new SimpleXMLElement($raw_returned_caption);
                                $counter = 0;
                                if(isset($capt->text))
                                {
                                    foreach($capt->text as $entry) 
                                    {
                                        if($counter == 0)
                                        {
                                            $returned_caption .= ucfirst($entry) . ' ';
                                        }
                                        else
                                        {
                                            $returned_caption .= $entry . ' ';
                                        }
                                        if($counter >= 8)
                                        {
                                            $returned_caption = rtrim($returned_caption);
                                            if(substr($returned_caption, -1) != '.')
                                            {
                                                $returned_caption .= '.';
                                            }
                                            $returned_caption .= '<br/><br/>';
                                            $counter = 0;
                                        }
                                        else
                                        {
                                            $counter++;
                                        }
                                    }
                                }
                                $returned_caption = trim($returned_caption);
                            }
                            if(!empty($max_caption) && strlen($returned_caption) > $max_caption)
                            {
                                $returned_caption = substr($returned_caption, 0, $max_caption);
                            }
                            if($returned_caption == '')
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse YouTube video caption for URL (no data returned): ' . $zurl
                                ];
                                aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => $returned_caption
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'url parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_youtube_search')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['keyword']))
                        {
                            $keyword = $tc['function']['arguments']['keyword'];
                            if (empty($keyword)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty keyword parameter provided'
                                ];
                                continue;
                            }
                            $new_vid = aiomatic_get_video($keyword);
                            if($new_vid == '')
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse YouTube video search results for keyword (no data returned): ' . $keyword
                                ];
                                aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'echo' => $new_vid,
                                    'output' => $new_vid
                                ];
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'keyword parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_facebook')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['content']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $fbomatic_active = false;
                            if (is_plugin_active('fbomatic-facebook-post-generator/fbomatic-facebook-post-generator.php')) 
                            {
                                $fbomatic_active = true;
                            }
                            if(!$fbomatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (F-omatic) not activated.'
                                ];
                                continue;
                            }
                            $furl = '';
                            if(isset($tc['function']['arguments']['url']))
                            {
                                $furl = $tc['function']['arguments']['url'];
                            }
                            $zcontent = $tc['function']['arguments']['content'];
                            if (empty($zcontent)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty content parameter provided'
                                ];
                                continue;
                            }
                            $page_to_post = '';
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['facebook_post_select']) && $aiomatic_Chatbot_Settings['facebook_post_select'] != '') {
                                $page_to_post = $aiomatic_Chatbot_Settings['facebook_post_select'];
                            }
                            if (empty($page_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'No page where to publish the post was selected'
                                ];
                                continue;
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = array('required_plugin' => array('fbomatic-facebook-post-generator/fbomatic-facebook-post-generator.php' => array('F-omatic Automatic Post Generator', 'https://1.envato.market/fbomatic')));
                            $return_me = aiomatic_post_to_facebook($card_type_found, $zcontent, $furl, $page_to_post);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Facebook posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse Facebook posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Facebook posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'content parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_image_facebook')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['image_url']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $fbomatic_active = false;
                            if (is_plugin_active('fbomatic-facebook-post-generator/fbomatic-facebook-post-generator.php')) 
                            {
                                $fbomatic_active = true;
                            }
                            if(!$fbomatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (F-omatic) not activated.'
                                ];
                                continue;
                            }
                            $caption = '';
                            if(isset($tc['function']['arguments']['caption']))
                            {
                                $caption = $tc['function']['arguments']['caption'];
                            }
                            $image_url = $tc['function']['arguments']['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty image_url parameter provided'
                                ];
                                continue;
                            }
                            $page_to_post = '';
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['facebook_post_select']) && $aiomatic_Chatbot_Settings['facebook_post_select'] != '') {
                                $page_to_post = $aiomatic_Chatbot_Settings['facebook_post_select'];
                            }
                            if (empty($page_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'No page where to publish the post was selected'
                                ];
                                continue;
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = array('required_plugin' => array('fbomatic-facebook-post-generator/fbomatic-facebook-post-generator.php' => array('F-omatic Automatic Post Generator', 'https://1.envato.market/fbomatic')));
                            $return_me = aiomatic_post_image_to_facebook($card_type_found, $caption, $image_url, $page_to_post);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Facebook image posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse Facebook image posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Facebook image posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'image_url parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_twitter')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['content']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $twitomatic_active = false;
                            if (is_plugin_active('twitomatic-twitter-post-generator/twitomatic-twitter-post-generator.php')) 
                            {
                                $twitomatic_active = true;
                            }
                            if(!$twitomatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (Twitomatic) not activated.'
                                ];
                                continue;
                            }
                            $image_url = '';
                            if(isset($tc['function']['arguments']['image_url']))
                            {
                                $image_url = $tc['function']['arguments']['image_url'];
                            }
                            $zcontent = $tc['function']['arguments']['content'];
                            if (empty($zcontent)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty content parameter provided'
                                ];
                                continue;
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = array('required_plugin' => array('twitomatic-twitter-post-generator/twitomatic-twitter-post-generator.php' => array('Twitomatic Automatic Post Generator', 'https://1.envato.market/twitomatic')));
                            $return_me = aiomatic_post_to_twitter($card_type_found, $zcontent, $image_url);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Twitter posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse Twitter posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Twitter posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'content parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_instagram')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['image_url']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $instamatic_active = false;
                            if (is_plugin_active('instamatic-instagram-post-generator/instamatic-instagram-post-generator.php')) 
                            {
                                $instamatic_active = true;
                            }
                            if(!$instamatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (Instamatic) not activated.'
                                ];
                                continue;
                            }
                            $zcontent = '';
                            if(isset($tc['function']['arguments']['content']))
                            {
                                $zcontent = $tc['function']['arguments']['content'];
                            }
                            $image_url = $tc['function']['arguments']['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty image_url parameter provided'
                                ];
                                continue;
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = array('required_plugin' => array('instamatic-instagram-post-generator/instamatic-instagram-post-generator.php' => array('iMediamatic Automatic Post Generator', 'https://1.envato.market/instamatic')));
                            $return_me = aiomatic_post_image_to_instagram($card_type_found, $zcontent, $image_url);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Instagram posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse Instagram posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Instagram posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'image_url parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_pinterest')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['image_url']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $instamatic_active = false;
                            if (is_plugin_active('pinterestomatic-pinterest-post-generator/pinterestomatic-pinterest-post-generator.php')) 
                            {
                                $instamatic_active = true;
                            }
                            if(!$instamatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (Pinterestomatic) not activated.'
                                ];
                                continue;
                            }
                            $page_to_post = '';
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['pinterest_post_select']) && $aiomatic_Chatbot_Settings['pinterest_post_select'] != '') {
                                $page_to_post = $aiomatic_Chatbot_Settings['pinterest_post_select'];
                            }
                            if (empty($page_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'No boards where to publish the post was selected'
                                ];
                                continue;
                            }
                            $description = '';
                            if(isset($tc['function']['arguments']['description']))
                            {
                                $description = $tc['function']['arguments']['description'];
                            }
                            $title = '';
                            if(isset($tc['function']['arguments']['title']))
                            {
                                $title = $tc['function']['arguments']['title'];
                            }
                            $pin_url = '';
                            if(isset($tc['function']['arguments']['pin_url']))
                            {
                                $pin_url = $tc['function']['arguments']['pin_url'];
                            }
                            $image_url = $tc['function']['arguments']['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty image_url parameter provided'
                                ];
                                continue;
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = array('required_plugin' => array('pinterestomatic-pinterest-post-generator/pinterestomatic-pinterest-post-generator.php' => array('Pinterestomatic Automatic Post Generator', 'https://1.envato.market/pinterestomatic')));
                            $return_me = aiomatic_post_image_to_pinterest($card_type_found, $description, $title, $pin_url, $image_url, $page_to_post);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Pinterest posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse Pinterest posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Pinterest posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'image_url parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_google_my_business')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['image_url']) && isset($tc['function']['arguments']['content']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $instamatic_active = false;
                            if (is_plugin_active('businessomatic-google-my-business-post-generator/businessomatic-google-my-business-post-generator.php')) 
                            {
                                $instamatic_active = true;
                            }
                            if(!$instamatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (Businessomatic) not activated.'
                                ];
                                continue;
                            }
                            $page_to_post = '';
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['business_post_select']) && $aiomatic_Chatbot_Settings['business_post_select'] != '') {
                                $page_to_post = $aiomatic_Chatbot_Settings['business_post_select'];
                            }
                            if (empty($page_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'No pages where to publish the post was selected'
                                ];
                                continue;
                            }
                            $content = $tc['function']['arguments']['content'];
                            $image_url = $tc['function']['arguments']['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty image_url parameter provided'
                                ];
                                continue;
                            }
                            if (empty($content)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty content parameter provided'
                                ];
                                continue;
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = array('required_plugin' => array('businessomatic-google-my-business-post-generator/businessomatic-google-my-business-post-generator.php' => array('Businessomatic Automatic Post Generator', 'https://1.envato.market/businessomatic')));
                            $return_me = aiomatic_post_to_gmb($card_type_found, $content, $image_url, $page_to_post);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'GMB posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse GMB posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'GMB posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'image_url or content parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_youtube_community')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['post_type']) && isset($tc['function']['arguments']['content']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $instamatic_active = false;
                            if (is_plugin_active('youtubomatic-youtube-post-generator/youtubomatic-youtube-post-generator.php')) 
                            {
                                $instamatic_active = true;
                            }
                            if(!$instamatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (Youtubomatic) not activated.'
                                ];
                                continue;
                            }
                            $content = $tc['function']['arguments']['content'];
                            if (empty($content)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty content parameter provided'
                                ];
                                continue;
                            }
                            $post_type = $tc['function']['arguments']['post_type'];
                            if (empty($post_type)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty post_type parameter provided'
                                ];
                                continue;
                            }
                            $post_type = trim($post_type);
                            if($post_type != 'image' && $post_type != 'text')
                            {
                                $post_type = 'text';
                            }
                            $image_url = '';
                            if(isset($tc['function']['arguments']['image_url']))
                            {
                                $image_url = $tc['function']['arguments']['image_url'];
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            if($image_url != '')
                            {
                                $media = array($image_url);
                            }
                            else
                            {
                                $media = array();
                            }
                            $card_type_found = array('required_plugin' => array('youtubomatic-youtube-post-generator/youtubomatic-youtube-post-generator.php' => array('Youtubomatic Automatic Post Generator', 'https://1.envato.market/youtubomatic')));
                            $return_me = aiomatic_post_to_youtube_community($card_type_found, $content, $post_type, $media);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'YouTube Community posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse YouTube Community posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'YouTube Community posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'post_type or content parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_reddit')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['title']) && isset($tc['function']['arguments']['content']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $instamatic_active = false;
                            if (is_plugin_active('redditomatic-reddit-post-generator/redditomatic-reddit-post-generator.php')) 
                            {
                                $instamatic_active = true;
                            }
                            if(!$instamatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (Redditomatic) not activated.'
                                ];
                                continue;
                            }
                            $content = $tc['function']['arguments']['content'];
                            if (empty($content)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty content parameter provided'
                                ];
                                continue;
                            }
                            $title = $tc['function']['arguments']['title'];
                            if (empty($title)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty title parameter provided'
                                ];
                                continue;
                            }
                            $subreddit_to_post = '';
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['subreddits_list']) && $aiomatic_Chatbot_Settings['subreddits_list'] != '') {
                                $subreddit_to_post = $aiomatic_Chatbot_Settings['subreddits_list'];
                            }
                            if (empty($subreddit_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'No subreddits were defined where to publish the post'
                                ];
                                continue;
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            if($image_url != '')
                            {
                                $media = array($image_url);
                            }
                            else
                            {
                                $media = array();
                            }
                            $card_type_found = array('required_plugin' => array('redditomatic-reddit-post-generator/redditomatic-reddit-post-generator.php' => array('Redditomatic Automatic Post Generator', 'https://1.envato.market/redditomatic')));
                            $return_me = aiomatic_post_to_reddit($card_type_found, $title, $content, 'auto', $subreddit_to_post);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Reddit posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse Reddit posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Reddit posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'title or content parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_publish_linkedin')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['title']) && isset($tc['function']['arguments']['content']))
                        {
                            if(!function_exists('is_plugin_active'))
                            {
                                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            }
                            $instamatic_active = false;
                            if (is_plugin_active('linkedinomatic-linkedin-post-generator/linkedinomatic-linkedin-post-generator.php')) 
                            {
                                $instamatic_active = true;
                            }
                            if(!$instamatic_active)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Required plugin (Linkedinomatic) not activated.'
                                ];
                                continue;
                            }
                            $content = $tc['function']['arguments']['content'];
                            if (empty($content)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty content parameter provided'
                                ];
                                continue;
                            }
                            $title = $tc['function']['arguments']['title'];
                            if (empty($title)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty title parameter provided'
                                ];
                                continue;
                            }
                            $description = '';
                            $post_link = '';
                            $image_url = '';
                            if(isset($tc['function']['arguments']['description']))
                            {
                                $description = $tc['function']['arguments']['description'];
                            }
                            if(isset($tc['function']['arguments']['link']))
                            {
                                $post_link = $tc['function']['arguments']['link'];
                            }
                            if(isset($tc['function']['arguments']['image_url']))
                            {
                                $image_url = $tc['function']['arguments']['image_url'];
                            }
                            $selected_pages = '';
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['linkedin_selected_pages']) && $aiomatic_Chatbot_Settings['linkedin_selected_pages'] != '') {
                                $selected_pages = $aiomatic_Chatbot_Settings['linkedin_selected_pages'];
                            }
                            if (empty($selected_pages)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'No pages selected where to post to LinkedIn'
                                ];
                                continue;
                            }
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = array('required_plugin' => array('linkedinomatic-linkedin-post-generator/linkedinomatic-linkedin-post-generator.php' => array('Linkedinomatic Automatic Post Generator', 'https://1.envato.market/linkedinomatic')));
                            $return_me = aiomatic_post_to_linkedin($card_type_found, $content, $image_url, $title, $post_link, $description, '1', $selected_pages);
                            if(isset($return_me['error']))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'LinkedIn posting failed: ' . $return_me['error']
                                ];
                                continue;
                            }
                            elseif(empty($return_me))
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to parse LinkedIn posting results'
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'LinkedIn posting successful: ' . json_encode($return_me, true)
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'title or content parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_send_email')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['subject']) && isset($tc['function']['arguments']['content']) && isset($tc['function']['arguments']['recipient_email']))
                        {
                            $subject = $tc['function']['arguments']['subject'];
                            $content = $tc['function']['arguments']['content'];
                            $recipient_email = $tc['function']['arguments']['recipient_email'];
                            if (empty($subject) || empty($content) || empty($recipient_email)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty subject, content or recipient_email parameter provided'
                                ];
                                continue;
                            }
                            $headers[] = 'From: AIomatic Plugin Chatbot <aiomatic@noreply.net>';
                            $headers[] = 'Reply-To: noreply@aiomatic.com';
                            $headers[] = 'X-Mailer: PHP/' . phpversion();
                            $headers[] = 'Content-Type: text/html';
                            $headers[] = 'Charset: ' . get_option('blog_charset', 'UTF-8');
                            $sent = wp_mail($recipient_email, $subject, $content, $headers);
                            if($sent === false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to send email to address: ' . $recipient_email
                                ];
                                continue;
                            }
                            else
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'OK'
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'subject, content or recipient_email parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
                elseif ($tc['function']['name'] === 'aiomatic_webhook')
                {
                    if (isset($tc['function']['arguments'])) 
                    {
                        $result = '';
                        if(isset($tc['function']['arguments']['webhook_url']) && isset($tc['function']['arguments']['method_selector']))
                        {
                            $webhook_url = $tc['function']['arguments']['webhook_url'];
                            $method_selector = $tc['function']['arguments']['method_selector'];
                            if (empty($webhook_url) || empty($method_selector)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Empty webhook_url or method_selector parameter provided'
                                ];
                                continue;
                            }
                            $content_type = '';
                            if(isset($tc['function']['arguments']['content_type']))
                            {
                                $content_type = $tc['function']['arguments']['content_type'];
                            }
                            $post_template = '';
                            if(isset($tc['function']['arguments']['data']))
                            {
                                $post_template = $tc['function']['arguments']['data'];
                            }
                            $headers_template = '';
                            if(isset($tc['function']['arguments']['headers']))
                            {
                                $headers_template = $tc['function']['arguments']['headers'];
                            }
                            
                            $urlParsed = parse_url( $webhook_url, PHP_URL_HOST );
                            if ( filter_var( $webhook_url, FILTER_VALIDATE_URL ) === FALSE || empty( $urlParsed ) )
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Invalid webhook_url entered ' . $webhook_url
                                ];
                                continue;
                            }
                            else if ( $content_type == 'JSON' && empty( json_decode( $post_template, TRUE ) ) )
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'The JSON data must be valid ' . $webhook_url
                                ];
                                continue;
                            }
                            $headers = [];
                            if(!empty($headers_template))
                            {
                                $headers_template_arr = preg_split('/\r\n|\r|\n/', trim($headers_template));
                                foreach($headers_template_arr as $arr_fr)
                                {
                                    if(!empty(trim($arr_fr)) && strstr($arr_fr, '=>'))
                                    {
                                        $small_arr = explode('=>', $arr_fr);
                                        $headers[] = trim($small_arr[0]) . ':' . trim($small_arr[1]);
                                    }
                                }
                            }
                            $content_params = [];
                            if(!empty($post_template))
                            {
                                $post_template_arr = preg_split('/\r\n|\r|\n/', trim($post_template));
                                foreach($post_template_arr as $arr_fr)
                                {
                                    if(!empty(trim($arr_fr)) && strstr($arr_fr, '=>'))
                                    {
                                        $small_arr = explode('=>', $arr_fr);
                                        $content_params[trim($small_arr[0])] = trim($small_arr[1]);
                                    }
                                }
                            }
                            $ch = curl_init();
                            if ($ch === false) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to init curl in webhook execution'
                                ];
                                continue;
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
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            if ($webhook_method == 'POST' || $webhook_method == 'PUT' || $webhook_method == 'DELETE') 
                            {
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $webhook_method);
                                if (!empty($content_params) && $content_type == 'form_data') {
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($content_params));
                                } elseif (!empty($post_template) && $content_type == 'JSON') {
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_template));
                                    $headers[] = 'Content-Type: application/json';
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                }
                            }
                            else
                            {
                                $query_string = http_build_query($content_params);
                                $webhook_url = $webhook_url . (strpos($webhook_url, '?') === false ? '?' : '&') . $query_string;
                            }
                            curl_setopt($ch, CURLOPT_URL, $webhook_url);

                            $response = curl_exec($ch);
                            if($response === false)
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Failed to send webhook request to ' . $webhook_url
                                ];
                                continue;
                            }
                            $err = curl_error($ch);
                            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            if ($err) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Webhook request error to URL ' . $webhook_url . ' - error: ' . $err
                                ];
                                continue;
                            }

                            if ($statusCode >= 200 && $statusCode <= 299) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Webhook URL called successfully!'
                                ];
                                continue;
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $tc['id'], 
                                    'output' => 'Webhook unexpected return code to URL ' . $webhook_url . ' - return code: ' . $statusCode
                                ];
                                continue;
                            }
                        }
                        else
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $tc['id'], 
                                'output' => 'subject, content or recipient_email parameter was not provided'
                            ];
                            aiomatic_log_to_file('Failed to decode assistant function calling: ' . print_r($tc, true));
                        }
                    }
                    else
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $tc['id'], 
                            'output' => 'Arguments not set for the function call'
                        ];
                    }
                }
            }
        }
    } else {
        throw new Exception("Required action details are missing or incomplete.");
    }
    return $toolOutputs;
}
function aiomatic_call_ollama_tool($response) 
{
    $toolOutputs = [];
    if (isset($response['message']['tool_calls'])) 
    {
        require_once(__DIR__ . '/res/amazon-direct.php');
        foreach ($response['message']['tool_calls'] as $zid => $tc) 
        {
            if (isset($tc['function'])) 
            {
                $function_name = $tc['function']['name'] ?? '';
                $arguments = $tc['function']['arguments'] ?? [];
                if ($function_name === 'aiomatic_wp_god_mode') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['called_function_name'])) 
                        {
                            $function_name = $arguments['called_function_name'];
                            $params = $arguments['parameter_array'] ?? [];
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            if (isset($aiomatic_Chatbot_Settings['god_whitelisted_functions']) && trim($aiomatic_Chatbot_Settings['god_whitelisted_functions']) != '') 
                            {
                                $white = array_filter(preg_split('/\r\n|\r|\n/', trim($aiomatic_Chatbot_Settings['god_whitelisted_functions'])));
                                if (!in_array($function_name, $white)) 
                                {
                                    if (isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] === 'on') 
                                    {
                                        aiomatic_log_to_file('Function call not allowed (Ollama), not whitelisted: ' . $function_name);
                                    }
                                    $toolOutputs[] = [
                                        'tool_call_id' => $zid, 
                                        'output' => 'You are not allowed to call this function (not on the whitelisted functions list)',
                                        'message' => $response['message']
                                    ];
                                    continue;
                                }
                            }
                            if (isset($aiomatic_Chatbot_Settings['god_blacklisted_functions']) && trim($aiomatic_Chatbot_Settings['god_blacklisted_functions']) != '') 
                            {
                                $black = array_filter(preg_split('/\r\n|\r|\n/', trim($aiomatic_Chatbot_Settings['god_blacklisted_functions'])));
                                if (in_array($function_name, $black)) 
                                {
                                    if (isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] === 'on') 
                                    {
                                        aiomatic_log_to_file('Function call not allowed (Ollama), blacklisted: ' . $function_name);
                                    }
                                    $toolOutputs[] = [
                                        'tool_call_id' => $zid, 
                                        'output' => 'You are not allowed to call this function (on the blacklisted functions list)',
                                        'message' => $response['message']
                                    ];
                                    continue;
                                }
                            }
                            if (function_exists($function_name)) 
                            {
                                if (!is_array($params)) 
                                {
                                    $jsony = json_decode($params, true);
                                    $params = $jsony !== null ? $jsony : (empty($params) ? [] : [$params]);
                                }
                                if (isset($params['post_title']) && $function_name === 'wp_insert_post') 
                                {
                                    $params = [$params];
                                }

                                $paramsAsString = aiomatic_format_function_params($params);
                                $reflection = new ReflectionFunction($function_name);
                                $requiredParamsCount = $reflection->getNumberOfRequiredParameters();

                                if (is_numeric($requiredParamsCount) && $requiredParamsCount > 0 && count($params) < $requiredParamsCount) 
                                {
                                    $result = $function_name . ' function has ' . $requiredParamsCount . ' required parameters, but only ' . count($params) . ' were passed to it.';
                                } 
                                else 
                                {
                                    if (isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] === 'on') 
                                    {
                                        aiomatic_log_to_file('Calling function (Ollama) ' . $function_name . '(' . $paramsAsString . ')...');
                                    }
                                    $result = call_user_func_array($function_name, $params);
                                }
                            } 
                            else 
                            {
                                $result = $function_name . ' function was not found on the system.';
                            }
                            if (isset($aiomatic_Main_Settings['enable_detailed_logging']) && $aiomatic_Main_Settings['enable_detailed_logging'] === 'on') 
                            {
                                $paramsAsString = aiomatic_format_function_params($params);
                                if ($result === false) 
                                {
                                    aiomatic_log_to_file('Function (Ollama) ' . $function_name . '(' . $paramsAsString . ') - returned false');
                                    $result = $function_name . ' returned false';
                                } 
                                elseif (empty($result)) 
                                {
                                    aiomatic_log_to_file('Function (Ollama) ' . $function_name . '(' . $paramsAsString . ') - returned an empty response: ' . print_r($result, true));
                                    $result = $function_name . ' returned an empty response';
                                } 
                                else 
                                {
                                    aiomatic_log_to_file('Function (Ollama) ' . $function_name . '(' . $paramsAsString . ') - result: ' . print_r($result, true));
                                }
                            }
                            if (is_object($result) || is_array($result)) 
                            {
                                $result = json_encode($result);
                            }
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => (string) $result,
                                'message' => $response['message']
                            ];
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'called_function_name parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($tc, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_image') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['prompt'])) 
                        {
                            $prompt = $arguments['prompt'];
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            
                            if (!isset($aiomatic_Main_Settings['app_id'])) 
                            {
                                $aiomatic_Main_Settings['app_id'] = '';
                            }
                            
                            $appids = array_filter(preg_split('/\r\n|\r|\n/', trim($aiomatic_Main_Settings['app_id'])));
                            $token = $appids[array_rand($appids)];
                            
                            if (empty($token)) 
                            {
                                aiomatic_log_to_file('You need to enter an OpenAI API key for this to work!');
                                $errorMessage = $aiomatic_Chatbot_Settings['god_mode_dalle_failed'] ?? 'Image creation failed, please try again later.';
                                
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => $errorMessage,
                                    'output' => $errorMessage,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                            
                            $image_size = $aiomatic_Chatbot_Settings['ai_image_size'] ?? '512x512';
                            $model = $aiomatic_Chatbot_Settings['ai_image_model'] ?? 'dalle2';
                            $aierror = '';
                            
                            $result = aiomatic_generate_ai_image($token, 1, $prompt, $image_size, 'chatFunctionDalleImage', false, 0, $aierror, $model);
                            
                            if ($result !== false && is_array($result)) 
                            {
                                foreach ($result as $tmpimg) 
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $zid, 
                                        'echo' => '<img class="image_max_w_ai" src="' . $tmpimg . '">',
                                        'output' => $tmpimg,
                                        'message' => $response['message']
                                    ];
                                    break;
                                }
                            } 
                            else 
                            {
                                aiomatic_log_to_file('Failed to generate Dall-E image in AI chatbot: ' . $aierror);
                                $errorMessage = $aiomatic_Chatbot_Settings['god_mode_dalle_failed'] ?? 'Image creation failed, please try again later.';
                                
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => $errorMessage,
                                    'output' => $errorMessage,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'prompt parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($tc, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_stable_image') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['prompt'])) 
                        {
                            $prompt = $arguments['prompt'];
                            if (empty($prompt)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty prompt query provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                            
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $image_size = $aiomatic_Chatbot_Settings['ai_image_size_stable'] ?? '512x512';
                            $height = $width = '512';
                            
                            if ($image_size === '1024x1024') 
                            {
                                $height = $width = '1024';
                            }
                            
                            $model = $aiomatic_Chatbot_Settings['stable_model'] ?? AIOMATIC_STABLE_DEFAULT_MODE;
                            $aierror = '';
                            
                            $airesult = aiomatic_generate_stability_image($prompt, $height, $width, 'chatFunctionStableImage', 0, false, $aierror, false, $model);
                            
                            if ($airesult !== false && isset($airesult[1])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => '<img class="image_max_w_ai" src="' . $airesult[1] . '">',
                                    'output' => $airesult[1],
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                aiomatic_log_to_file('Failed to generate Stable Diffusion image in AI chatbot: ' . $aierror);
                                $errorMessage = $aiomatic_Chatbot_Settings['god_mode_stable_failed'] ?? 'Image creation failed, please try again later.';
                                
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => $errorMessage,
                                    'output' => $errorMessage,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'prompt parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($tc, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_midjourney_image') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['prompt'])) 
                        {
                            $prompt = $arguments['prompt'];
                            if (empty($prompt)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty prompt query provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $height = '1024';
                            $width = '1024';
                            $aierror = '';
                            $airesult = aiomatic_generate_ai_image_midjourney($prompt, $width, $height, 'chatFunctionMidjourneyImage', false, $aierror);
                            
                            if ($airesult !== false) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => '<img class="image_max_w_ai" src="' . $airesult . '">',
                                    'output' => $airesult,
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                aiomatic_log_to_file('Failed to generate Midjourney image in AI chatbot: ' . $aierror);
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => 'Image creation failed, please try again later.',
                                    'output' => 'Image creation failed, please try again later.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'prompt parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($tc, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_replicate_image') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['prompt'])) 
                        {
                            $prompt = $arguments['prompt'];
                            if (empty($prompt)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty prompt query provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $height = '1024';
                            $width = '1024';
                            $aierror = '';
                            $airesult = aiomatic_generate_replicate_image($prompt, $width, $height, 'chatFunctionReplicateImage', false, $aierror);
                            
                            if ($airesult !== false) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => '<img class="image_max_w_ai" src="' . $airesult . '">',
                                    'output' => $airesult,
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                aiomatic_log_to_file('Failed to generate Replicate image in AI chatbot: ' . $aierror);
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => 'Image creation failed, please try again later.',
                                    'output' => 'Image creation failed, please try again later.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'prompt parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($tc, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_stable_video') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['image_url'])) 
                        {
                            $image_url = $arguments['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty image_url query provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $image_size = $aiomatic_Chatbot_Settings['ai_video_size_stable'] ?? '768x768';
                            $aierror = '';
                            $response_text = aiomatic_generate_stability_video($image_url, $image_size, 'chatbotStableVideo', 0, false, $aierror, false);
                            
                            if ($response_text !== false && isset($response_text[1])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => '<div style="padding-bottom:56.25%; position:relative; display:block; width: 100%"><iframe src="' . $response_text[1] . '" width="100%" height="100%" style="position:absolute; top:0; left: 0" allowfullscreen webkitallowfullscreen frameborder="0"></iframe></div>',
                                    'output' => $response_text[1],
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                aiomatic_log_to_file('Failed to generate Stable Diffusion video in AI chatbot: ' . $aierror);
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => 'Video creation failed, please try again later.',
                                    'output' => 'Video creation failed, please try again later.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'image_url parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($tc, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_amazon_listing') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['query'])) 
                        {
                            $asin = $arguments['query'];
                            if (empty($asin)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty search query provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $aff_id = $aiomatic_Chatbot_Settings['affiliate_id'] ?? '';
                            $target_country = $aiomatic_Chatbot_Settings['target_country'] ?? 'com';
                            $max_product_count = $aiomatic_Chatbot_Settings['max_products'] ?? '3-4';
                            $amaz_sort_results = $aiomatic_Chatbot_Settings['sort_results'] ?? 'none';
                            $listing_template = $aiomatic_Chatbot_Settings['listing_template'] ?? '%%product_counter%%. %%product_title%% - Description: %%product_description%% - Link: %%product_url%% - Price: %%product_price%%';
                
                            // Handle the max product count range
                            if (strstr($max_product_count, '-') !== false) 
                            {
                                $pr_arr = explode('-', $max_product_count);
                                $minx = trim($pr_arr[0]);
                                $maxx = trim($pr_arr[1]);
                                $max_product_count = (is_numeric($minx) && is_numeric($maxx)) ? rand(intval($minx), intval($maxx)) : (is_numeric($minx) ? intval($minx) : (is_numeric($maxx) ? intval($maxx) : 100));
                            }
                            $max_prod = is_numeric($max_product_count) ? intval($max_product_count) : 100;
                
                            $amazresult = aiomatic_amazon_get_post($asin, trim($aff_id), $target_country, '', '', $amaz_sort_results, $max_prod, '1', array());
                            
                            if (is_array($amazresult) && ((isset($amazresult['status']) && $amazresult['status'] === 'nothing') || count($amazresult) == 0)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'No Amazon products found for query: ' . $asin,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                            if (!is_array($amazresult)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'An error occurred while searching Amazon for: ' . $asin,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $final_result = '';
                            $counter = 1;
                            foreach ($amazresult as $myprod) 
                            {
                                $copy_template = $listing_template;
                                $copy_template = str_replace('%%product_counter%%', $counter, $copy_template);
                                $copy_template = str_replace('%%product_title%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_title)), $copy_template);
                                $copy_template = str_replace('%%product_description%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_desc)), $copy_template);
                                $copy_template = str_replace('%%product_url%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_url)), $copy_template);
                                $copy_template = str_replace('%%product_price%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_price)), $copy_template);
                                $copy_template = str_replace('%%product_list_price%%', trim(preg_replace('/\s+/', ' ', $myprod->product_list_price)), $copy_template);
                                $copy_template = str_replace('%%product_image%%', trim(preg_replace('/\s+/', ' ', $myprod->offer_img)), $copy_template);
                                $copy_template = str_replace('%%product_cart_url%%', trim(preg_replace('/\s+/', ' ', $myprod->cart_url)), $copy_template);
                                $copy_template = str_replace('%%product_images_urls%%', trim(preg_replace('/\s+/', ' ', $myprod->product_imgs)), $copy_template);
                                $copy_template = str_replace('%%product_images%%', trim(preg_replace('/\s+/', ' ', $myprod->product_imgs_html)), $copy_template);
                                $copy_template = str_replace('%%product_reviews%%', trim(preg_replace('/\s+/', ' ', implode(PHP_EOL, $myprod->item_reviews))), $copy_template);
                                // Additional replacements
                                $copy_template = str_replace('%%product_score%%', trim(preg_replace('/\s+/', ' ', $myprod->item_score)), $copy_template);
                                $copy_template = str_replace('%%product_language%%', trim(preg_replace('/\s+/', ' ', $myprod->language)), $copy_template);
                                $copy_template = str_replace('%%product_edition%%', trim(preg_replace('/\s+/', ' ', $myprod->edition)), $copy_template);
                                $copy_template = str_replace('%%product_pages_count%%', trim(preg_replace('/\s+/', ' ', $myprod->pages_count)), $copy_template);
                                $copy_template = str_replace('%%product_publication_date%%', trim(preg_replace('/\s+/', ' ', $myprod->publication_date)), $copy_template);
                                $copy_template = str_replace('%%product_contributors%%', trim(preg_replace('/\s+/', ' ', $myprod->contributors)), $copy_template);
                                $copy_template = str_replace('%%product_manufacturer%%', trim(preg_replace('/\s+/', ' ', $myprod->manufacturer)), $copy_template);
                                $copy_template = str_replace('%%product_binding%%', trim(preg_replace('/\s+/', ' ', $myprod->binding)), $copy_template);
                                $copy_template = str_replace('%%product_product_group%%', trim(preg_replace('/\s+/', ' ', $myprod->product_group)), $copy_template);
                                $copy_template = str_replace('%%product_rating%%', trim(preg_replace('/\s+/', ' ', $myprod->rating)), $copy_template);
                                $copy_template = str_replace('%%product_ean%%', trim(preg_replace('/\s+/', ' ', $myprod->eans)), $copy_template);
                                $copy_template = str_replace('%%product_part_no%%', trim(preg_replace('/\s+/', ' ', $myprod->part_no)), $copy_template);
                                $copy_template = str_replace('%%product_model%%', trim(preg_replace('/\s+/', ' ', $myprod->model)), $copy_template);
                                $copy_template = str_replace('%%product_warranty%%', trim(preg_replace('/\s+/', ' ', $myprod->warranty)), $copy_template);
                                $copy_template = str_replace('%%product_color%%', trim(preg_replace('/\s+/', ' ', $myprod->color)), $copy_template);
                                $copy_template = str_replace('%%product_is_adult%%', trim(preg_replace('/\s+/', ' ', $myprod->is_adult)), $copy_template);
                                $copy_template = str_replace('%%product_dimensions%%', trim(preg_replace('/\s+/', ' ', $myprod->dimensions)), $copy_template);
                                $copy_template = str_replace('%%product_size%%', trim(preg_replace('/\s+/', ' ', $myprod->size)), $copy_template);
                                $copy_template = str_replace('%%product_unit_count%%', trim(preg_replace('/\s+/', ' ', $myprod->unit_count)), $copy_template);
                
                                $counter++;
                                $final_result .= $copy_template . '\r\n';
                            }
                
                            $final_result = trim($final_result);
                            if (!empty($final_result)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => $final_result,
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Amazon did not return info for this query: ' . $asin,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'query parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($tc, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_amazon_product_details') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['query'])) 
                        {
                            $asin = $arguments['query'];
                            if (empty($asin)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty search query provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $aff_id = $aiomatic_Chatbot_Settings['affiliate_id'] ?? '';
                            $target_country = $aiomatic_Chatbot_Settings['target_country'] ?? 'com';
                            $max_prod = 1;
                
                            $amazresult = aiomatic_amazon_get_post($asin, trim($aff_id), $target_country, '', '', '', $max_prod, '1', array());
                
                            if (is_array($amazresult) && ((isset($amazresult['status']) && $amazresult['status'] === 'nothing') || count($amazresult) == 0)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'No Amazon products found for query: ' . $asin,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                            if (!is_array($amazresult)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'An error occurred while searching Amazon for: ' . $asin,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                            else 
                            {
                                $product = $amazresult[0];
                                $final_result = 'Product title: ' . $product->offer_title . '\n';
                                $final_result .= 'Description: ' . $product->offer_desc . '\n';
                                $final_result .= 'URL: ' . $product->offer_url . '\n';
                                $final_result .= 'Price: ' . $product->offer_price . '\n';
                                $final_result .= 'Listing Price: ' . $product->product_list_price . '\n';
                                $final_result .= 'Image: ' . $product->offer_img . '\n';
                                $final_result .= 'Add to cart URL: ' . $product->cart_url . '\n';
                                $final_result .= 'Other images: ' . $product->product_imgs . '\n';
                
                                // Additional details
                                if (!empty($product->item_score)) 
                                {
                                    $final_result .= 'Score: ' . $product->item_score . '\n';
                                }
                                if (!empty($product->language)) 
                                {
                                    $final_result .= 'Language: ' . $product->language . '\n';
                                }
                                if (!empty($product->edition)) 
                                {
                                    $final_result .= 'Edition: ' . $product->edition . '\n';
                                }
                                if (!empty($product->pages_count)) 
                                {
                                    $final_result .= 'Pages Count: ' . $product->pages_count . '\n';
                                }
                                if (!empty($product->publication_date)) 
                                {
                                    $final_result .= 'Date: ' . $product->publication_date . '\n';
                                }
                                if (!empty($product->contributors)) 
                                {
                                    $final_result .= 'Contributors: ' . $product->contributors . '\n';
                                }
                                if (!empty($product->manufacturer)) 
                                {
                                    $final_result .= 'Manufacturer: ' . $product->manufacturer . '\n';
                                }
                                if (!empty($product->binding)) 
                                {
                                    $final_result .= 'Binding: ' . $product->binding . '\n';
                                }
                                if (!empty($product->product_group)) 
                                {
                                    $final_result .= 'Product Group: ' . $product->product_group . '\n';
                                }
                                if (!empty($product->rating)) 
                                {
                                    $final_result .= 'Rating: ' . $product->rating . '\n';
                                }
                                if (!empty($product->eans)) 
                                {
                                    $final_result .= 'EAN: ' . $product->eans . '\n';
                                }
                                if (!empty($product->part_no)) 
                                {
                                    $final_result .= 'Part No: ' . $product->part_no . '\n';
                                }
                                if (!empty($product->model)) 
                                {
                                    $final_result .= 'Model: ' . $product->model . '\n';
                                }
                                if (!empty($product->warranty)) 
                                {
                                    $final_result .= 'Warranty: ' . $product->warranty . '\n';
                                }
                                if (!empty($product->color)) 
                                {
                                    $final_result .= 'Color: ' . $product->color . '\n';
                                }
                                if (!empty($product->is_adult)) 
                                {
                                    $final_result .= 'Is Adult: ' . $product->is_adult . '\n';
                                }
                                if (!empty($product->dimensions)) 
                                {
                                    $final_result .= 'Dimensions: ' . $product->dimensions . '\n';
                                }
                                if (!empty($product->size)) 
                                {
                                    $final_result .= 'Size: ' . $product->size . '\n';
                                }
                                if (!empty($product->unit_count)) 
                                {
                                    $final_result .= 'Unit Count: ' . $product->unit_count . '\n';
                                }
                                $final_result .= 'Reviews: ' . implode(PHP_EOL, $product->item_reviews) . '\n';
                
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => $final_result,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'query parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($tc, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_website_scraper') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['url'])) 
                        {
                            $scurl = $arguments['url'];
                            if (empty($scurl)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty scrape url provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $scrape_method = $aiomatic_Chatbot_Settings['scrape_method'] ?? '0';
                            $max_chars = $aiomatic_Chatbot_Settings['max_chars'] ?? '';
                            $scrape_selector = 'auto';
                            $scrape_string = '';
                            $strip_tags = ($aiomatic_Chatbot_Settings['strip_tags'] ?? '') === 'on' ? '1' : '0';
                
                            $scraped_data = aiomatic_scrape_page($scurl, $scrape_method, $scrape_selector, $scrape_string);
                            
                            if ($scraped_data === false) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to scrape website URL: ' . $scurl,
                                    'message' => $response['message']
                                ];
                                aiomatic_log_to_file('Failed to scrape website: ' . print_r($arguments, true));
                            } 
                            else 
                            {
                                if ($strip_tags === '1') 
                                {
                                    $scraped_data = wp_strip_all_tags($scraped_data);
                                } 
                                else 
                                {
                                    $scraped_data = aiomatic_fix_relative_links($scraped_data, $scurl);
                                }
                
                                if (!empty($max_chars) && is_numeric($max_chars)) 
                                {
                                    $scraped_data = (strlen($scraped_data) > intval($max_chars)) ? substr($scraped_data, 0, intval($max_chars)) : $scraped_data;
                                }
                
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => $scraped_data,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'url parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_rss_parser') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['url'])) 
                        {
                            $scurl = $arguments['url'];
                            if (empty($scurl)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty RSS feed URL provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $max_rss_items = isset($aiomatic_Chatbot_Settings['max_rss_items']) ? intval(trim($aiomatic_Chatbot_Settings['max_rss_items'])) : PHP_INT_MAX;
                            $rss_template = $aiomatic_Chatbot_Settings['rss_template'] ?? '[%%item_counter%%]: %%item_title%% - %%item_description%%';
                
                            // Load the RSS parser library
                            try 
                            {
                                if (!class_exists('SimplePie_Autoloader', false)) 
                                {
                                    require_once(dirname(__FILE__) . "/res/simplepie/autoloader.php");
                                }
                            } 
                            catch (Exception $e) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to load RSS parser library',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $feed = new SimplePie();
                            $feed->set_timeout(120);
                            $feed->set_feed_url($scurl);
                            $feed->enable_cache(false);
                            $feed->strip_htmltags(false);
                            $feed->init();
                            $feed->handle_content_type();
                
                            if ($feed->error()) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Error in parsing RSS feed: ' . $feed->error(),
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $final_result = '';
                            $items = $feed->get_items();
                            $rss_feeds = [];
                            foreach ($items as $itemx) 
                            {
                                $post_link = trim($itemx->get_permalink());
                                $user_name = ($fauthor = $itemx->get_author()) ? $fauthor->get_name() : '';
                                $feed_cats = array_map(fn($xcategory) => $xcategory->get_label(), $itemx->get_categories());
                                $post_cats = implode(',', $feed_cats);
                                $post_excerpt = $itemx->get_description();
                                $final_content = $itemx->get_content();
                                $rss_feeds[$itemx->get_title()] = [
                                    'url' => $post_link, 
                                    'author' => $user_name,  
                                    'cats' => $post_cats, 
                                    'excerpt' => $post_excerpt, 
                                    'content' => $final_content
                                ];
                            }
                
                            $template_copy = '';
                            $processed = 0;
                            foreach ($rss_feeds as $rtitle => $this_rss) 
                            {
                                if (!empty($max_rss_items) && $processed >= $max_rss_items) 
                                {
                                    break;
                                }
                
                                $template_copy = $rss_template;
                                $template_copy = str_replace('%%item_counter%%', $processed + 1, $template_copy);
                                $template_copy = str_replace('%%item_title%%', $rtitle, $template_copy);
                                $template_copy = str_replace('%%item_content%%', $this_rss['content'], $template_copy);
                                $template_copy = str_replace('%%item_description%%', $this_rss['excerpt'], $template_copy);
                                $template_copy = str_replace('%%item_url%%', $this_rss['url'], $template_copy);
                                $template_copy = str_replace('%%item_author%%', $this_rss['author'], $template_copy);
                                $template_copy = str_replace('%%item_categories%%', $this_rss['cats'], $template_copy);
                
                                if (!empty($template_copy)) 
                                {
                                    $final_result .= $template_copy . PHP_EOL;
                                }
                                $processed++;
                            }
                
                            if ($final_result === '') 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse RSS URL (no data returned): ' . $scurl,
                                    'message' => $response['message']
                                ];
                                aiomatic_log_to_file('Failed to parse RSS feed: ' . print_r($arguments, true));
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => $final_result,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'url parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_google_parser') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['keywords'])) 
                        {
                            $keywords = $arguments['keywords'];
                            if (empty($keywords)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty keywords parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $max_google_items = isset($aiomatic_Chatbot_Settings['max_google_items']) ? intval(trim($aiomatic_Chatbot_Settings['max_google_items'])) : PHP_INT_MAX;
                            $google_template = $aiomatic_Chatbot_Settings['google_template'] ?? '[%%item_counter%%]: %%item_title%% - %%item_snippet%%';
                
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $locale = $aiomatic_Main_Settings['internet_gl'] ?? '';
                            $internet_rez = aiomatic_internet_result($keywords, true, $locale);
                
                            $processed = 0;
                            $final_res = '';
                            foreach ($internet_rez as $emb) 
                            {
                                if (!empty($max_google_items) && $processed >= $max_google_items) 
                                {
                                    break;
                                }
                                $template_copy = $google_template;
                                $template_copy = str_replace('%%item_counter%%', $processed + 1, $template_copy);
                                $template_copy = str_replace('%%item_title%%', $emb['title'], $template_copy);
                                $template_copy = str_replace('%%item_snippet%%', $emb['snippet'], $template_copy);
                                $template_copy = str_replace('%%item_url%%', $emb['link'], $template_copy);
                                $final_res .= $template_copy . PHP_EOL;
                                $processed++;
                            }
                
                            if ($final_res === '') 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse Google SERP for keyword (no data returned): ' . $keywords,
                                    'message' => $response['message']
                                ];
                                aiomatic_log_to_file('Failed to parse Google SERP: ' . print_r($arguments, true));
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => $final_res,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'keywords parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_royalty_free_image') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['keyword'])) 
                        {
                            $keyword = $arguments['keyword'];
                            if (empty($keyword)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty keyword parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $raw_img_list = [];
                            $full_result_list = [];
                            $temp_img_attr = '';
                
                            $temp_get_img = aiomatic_get_free_image($aiomatic_Main_Settings, $keyword, $temp_img_attr, 10, false, $raw_img_list, [], $full_result_list);
                
                            if ($temp_get_img === false) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to get royalty free image for keyword: ' . $keyword,
                                    'message' => $response['message']
                                ];
                                aiomatic_log_to_file('Failed to get royalty free image: ' . print_r($arguments, true));
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => '<img class="image_max_w_ai" src="' . $temp_get_img . '">',
                                    'output' => $temp_get_img,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'keyword parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_youtube_captions') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['url'])) 
                        {
                            $zurl = $arguments['url'];
                            if (empty($zurl)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty url parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $aiomatic_Main_Settings = get_option('aiomatic_Main_Settings', false);
                            $default_lang = [];
                            $returned_caption = '';
                            $za_video_page = '';
                
                            $ch = curl_init();
                            if ($ch !== false) 
                            {
                                // Set proxy settings if available
                                if (!empty($aiomatic_Main_Settings['proxy_url']) && $aiomatic_Main_Settings['proxy_url'] !== 'disable') 
                                {
                                    $prx = explode(',', $aiomatic_Main_Settings['proxy_url']);
                                    $randomness = array_rand($prx);
                                    curl_setopt($ch, CURLOPT_PROXY, trim($prx[$randomness]));
                                    if (!empty($aiomatic_Main_Settings['proxy_auth'])) 
                                    {
                                        $prx_auth = explode(',', $aiomatic_Main_Settings['proxy_auth']);
                                        if (!empty($prx_auth[$randomness])) 
                                        {
                                            curl_setopt($ch, CURLOPT_PROXYUSERPWD, trim($prx_auth[$randomness]));
                                        }
                                    }
                                }
                                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                                $ztime = !empty($aiomatic_Main_Settings['max_timeout']) ? intval($aiomatic_Main_Settings['max_timeout']) : 300;
                                curl_setopt($ch, CURLOPT_TIMEOUT, $ztime);
                                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                                curl_setopt($ch, CURLOPT_REFERER, get_site_url());
                                curl_setopt($ch, CURLOPT_URL, $zurl);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                $za_video_page = curl_exec($ch);
                                curl_close($ch);
                
                                if ($za_video_page === false) 
                                {
                                    $toolOutputs[] = [
                                        'tool_call_id' => $zid, 
                                        'output' => 'Failed to download video URL: ' . $zurl,
                                        'message' => $response['message']
                                    ];
                                    continue;
                                }
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to init curl in YouTube caption importing: ' . $zurl,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            // Parse captions
                            if ($za_video_page !== false && strpos($za_video_page, 'vssId') !== false) 
                            {
                                $srt_dl_link = '';
                                preg_match_all('#{"baseUrl":"([^"]+?)","name":(?:.*?),"vssId":"a?\.([^"]+?)","languageCode":"(?:[^"]+?)",(?:"kind":"asr",)?"isTranslatable":(?:[^}]+?)}#i', $za_video_page, $zmatches);
                
                                if (!empty($zmatches[1][0])) 
                                {
                                    $eng_f = in_array('en', $zmatches[2]);
                                    for ($i = 0; $i < count($zmatches[1]); $i++) 
                                    {
                                        if (!empty($default_lang) && in_array($zmatches[2][$i], $default_lang)) 
                                        {
                                            $srt_dl_link = str_replace('\u0026', '&', $zmatches[1][$i]);
                                            break;
                                        } 
                                        elseif (!$eng_f || $zmatches[2][$i] === 'en') 
                                        {
                                            $srt_dl_link = str_replace('\u0026', '&', $zmatches[1][$i]);
                                            break;
                                        }
                                    }
                
                                    if ($srt_dl_link !== '') 
                                    {
                                        $ch = curl_init();
                                        if ($ch !== false) 
                                        {
                                            // Set proxy settings if available
                                            if (!empty($aiomatic_Main_Settings['proxy_url']) && $aiomatic_Main_Settings['proxy_url'] !== 'disable') 
                                            {
                                                $prx = explode(',', $aiomatic_Main_Settings['proxy_url']);
                                                $randomness = array_rand($prx);
                                                curl_setopt($ch, CURLOPT_PROXY, trim($prx[$randomness]));
                                                if (!empty($aiomatic_Main_Settings['proxy_auth'])) 
                                                {
                                                    $prx_auth = explode(',', $aiomatic_Main_Settings['proxy_auth']);
                                                    if (!empty($prx_auth[$randomness])) 
                                                    {
                                                        curl_setopt($ch, CURLOPT_PROXYUSERPWD, trim($prx_auth[$randomness]));
                                                    }
                                                }
                                            }
                                            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                                            $ztime = !empty($aiomatic_Main_Settings['max_timeout']) ? intval($aiomatic_Main_Settings['max_timeout']) : 300;
                                            curl_setopt($ch, CURLOPT_TIMEOUT, $ztime);
                                            curl_setopt($ch, CURLOPT_HTTPGET, 1);
                                            curl_setopt($ch, CURLOPT_REFERER, get_site_url());
                                            curl_setopt($ch, CURLOPT_URL, $srt_dl_link);
                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                            $xza_video_page = curl_exec($ch);
                                            curl_close($ch);
                
                                            if (!empty($xza_video_page)) 
                                            {
                                                $returned_caption = preg_replace('#\s+#', ' ', $xza_video_page);
                                            }
                                        } 
                                        else 
                                        {
                                            aiomatic_log_to_file('Failed to init curl in subtitle listing: ' . $zurl);
                                        }
                                    }
                                }
                            }
                
                            // Process the returned captions
                            if (empty($returned_caption)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to find subtitles for video URL: ' . $zurl,
                                    'message' => $response['message']
                                ];
                            } 
                            else if (stripos($returned_caption, 'transcript') !== false) 
                            {
                                $raw_returned_caption = preg_replace('#\s*?(?:start|dur)="[\d.]*?"\s*?#', '', $returned_caption);
                                $raw_returned_caption = preg_replace('#\[[A-Z][a-z]+\]#', '', $raw_returned_caption);
                                $returned_caption = '';
                                $capt = new SimpleXMLElement($raw_returned_caption);
                                $counter = 0;
                
                                if (isset($capt->text)) 
                                {
                                    foreach ($capt->text as $entry) 
                                    {
                                        $returned_caption .= ($counter === 0 ? ucfirst($entry) : $entry) . ' ';
                                        if (++$counter >= 8) 
                                        {
                                            $returned_caption = rtrim($returned_caption);
                                            if (substr($returned_caption, -1) !== '.') 
                                            {
                                                $returned_caption .= '.';
                                            }
                                            $returned_caption .= '<br/><br/>';
                                            $counter = 0;
                                        }
                                    }
                                }
                                $returned_caption = trim($returned_caption);
                            }
                
                            if (empty($returned_caption)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse YouTube video caption for URL (no data returned): ' . $zurl,
                                    'message' => $response['message']
                                ];
                                aiomatic_log_to_file('Failed to decode YouTube captions: ' . print_r($arguments, true));
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => $returned_caption,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'url parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_youtube_search') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['keyword'])) 
                        {
                            $keyword = $arguments['keyword'];
                            if (empty($keyword)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty keyword parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $new_vid = aiomatic_get_video($keyword);
                            if ($new_vid == '') 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse YouTube video search results for keyword (no data returned): ' . $keyword,
                                    'message' => $response['message']
                                ];
                                aiomatic_log_to_file('Failed to decode YouTube search: ' . print_r($arguments, true));
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'echo' => $new_vid,
                                    'output' => $new_vid,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'keyword parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_publish_facebook') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['content'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $fbomatic_active = is_plugin_active('fbomatic-facebook-post-generator/fbomatic-facebook-post-generator.php');
                            if (!$fbomatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (F-omatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $furl = $arguments['url'] ?? '';
                            $zcontent = $arguments['content'];
                            if (empty($zcontent)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty content parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $page_to_post = $aiomatic_Chatbot_Settings['facebook_post_select'] ?? '';
                
                            if (empty($page_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'No page where to publish the post was selected',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'fbomatic-facebook-post-generator/fbomatic-facebook-post-generator.php' => [
                                        'F-omatic Automatic Post Generator', 
                                        'https://1.envato.market/fbomatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_to_facebook($card_type_found, $zcontent, $furl, $page_to_post);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Facebook posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse Facebook posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Facebook posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'content parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_publish_image_facebook') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['image_url'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $fbomatic_active = is_plugin_active('fbomatic-facebook-post-generator/fbomatic-facebook-post-generator.php');
                            if (!$fbomatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (F-omatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $caption = $arguments['caption'] ?? '';
                            $image_url = $arguments['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty image_url parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $page_to_post = $aiomatic_Chatbot_Settings['facebook_post_select'] ?? '';
                
                            if (empty($page_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'No page where to publish the post was selected',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'fbomatic-facebook-post-generator/fbomatic-facebook-post-generator.php' => [
                                        'F-omatic Automatic Post Generator', 
                                        'https://1.envato.market/fbomatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_image_to_facebook($card_type_found, $caption, $image_url, $page_to_post);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Facebook image posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse Facebook image posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Facebook image posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'image_url parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_publish_twitter') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['content'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $twitomatic_active = is_plugin_active('twitomatic-twitter-post-generator/twitomatic-twitter-post-generator.php');
                            if (!$twitomatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (Twitomatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $image_url = $arguments['image_url'] ?? '';
                            $zcontent = $arguments['content'];
                            if (empty($zcontent)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty content parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'twitomatic-twitter-post-generator/twitomatic-twitter-post-generator.php' => [
                                        'Twitomatic Automatic Post Generator', 
                                        'https://1.envato.market/twitomatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_to_twitter($card_type_found, $zcontent, $image_url);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Twitter posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse Twitter posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Twitter posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'content parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_publish_instagram') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['image_url'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $instamatic_active = is_plugin_active('instamatic-instagram-post-generator/instamatic-instagram-post-generator.php');
                            if (!$instamatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (Instamatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $zcontent = $arguments['content'] ?? '';
                            $image_url = $arguments['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty image_url parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'instamatic-instagram-post-generator/instamatic-instagram-post-generator.php' => [
                                        'iMediamatic Automatic Post Generator', 
                                        'https://1.envato.market/instamatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_image_to_instagram($card_type_found, $zcontent, $image_url);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Instagram posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse Instagram posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Instagram posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'image_url parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_publish_pinterest') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['image_url'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $pinterestomatic_active = is_plugin_active('pinterestomatic-pinterest-post-generator/pinterestomatic-pinterest-post-generator.php');
                            if (!$pinterestomatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (Pinterestomatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $page_to_post = $aiomatic_Chatbot_Settings['pinterest_post_select'] ?? '';
                
                            if (empty($page_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'No boards where to publish the post were selected',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $description = $arguments['description'] ?? '';
                            $title = $arguments['title'] ?? '';
                            $pin_url = $arguments['pin_url'] ?? '';
                            $image_url = $arguments['image_url'];
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty image_url parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'pinterestomatic-pinterest-post-generator/pinterestomatic-pinterest-post-generator.php' => [
                                        'Pinterestomatic Automatic Post Generator', 
                                        'https://1.envato.market/pinterestomatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_image_to_pinterest($card_type_found, $description, $title, $pin_url, $image_url, $page_to_post);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Pinterest posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse Pinterest posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Pinterest posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'image_url parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_publish_google_my_business') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['image_url']) && isset($arguments['content'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $businessomatic_active = is_plugin_active('businessomatic-google-my-business-post-generator/businessomatic-google-my-business-post-generator.php');
                            if (!$businessomatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (Businessomatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $page_to_post = $aiomatic_Chatbot_Settings['business_post_select'] ?? '';
                
                            if (empty($page_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'No pages where to publish the post was selected',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $content = $arguments['content'];
                            $image_url = $arguments['image_url'];
                
                            if (empty($image_url)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty image_url parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            if (empty($content)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty content parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'businessomatic-google-my-business-post-generator/businessomatic-google-my-business-post-generator.php' => [
                                        'Businessomatic Automatic Post Generator', 
                                        'https://1.envato.market/businessomatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_to_gmb($card_type_found, $content, $image_url, $page_to_post);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'GMB posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse GMB posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'GMB posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'image_url or content parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_publish_youtube_community') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['post_type']) && isset($arguments['content'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $youtubomatic_active = is_plugin_active('youtubomatic-youtube-post-generator/youtubomatic-youtube-post-generator.php');
                            if (!$youtubomatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (Youtubomatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $content = $arguments['content'];
                            $post_type = trim($arguments['post_type']);
                
                            if (empty($content)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty content parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            if (empty($post_type) || !in_array($post_type, ['image', 'text'])) 
                            {
                                $post_type = 'text';
                            }
                
                            $image_url = $arguments['image_url'] ?? '';
                            $media = $image_url ? [$image_url] : [];
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'youtubomatic-youtube-post-generator/youtubomatic-youtube-post-generator.php' => [
                                        'Youtubomatic Automatic Post Generator', 
                                        'https://1.envato.market/youtubomatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_to_youtube_community($card_type_found, $content, $post_type, $media);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'YouTube Community posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse YouTube Community posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'YouTube Community posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'post_type or content parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_publish_reddit') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['title']) && isset($arguments['content'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $redditomatic_active = is_plugin_active('redditomatic-reddit-post-generator/redditomatic-reddit-post-generator.php');
                            if (!$redditomatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (Redditomatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $content = $arguments['content'];
                            $title = $arguments['title'];
                
                            if (empty($content)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty content parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            if (empty($title)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty title parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $subreddit_to_post = $aiomatic_Chatbot_Settings['subreddits_list'] ?? '';
                
                            if (empty($subreddit_to_post)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'No subreddits were defined where to publish the post',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'redditomatic-reddit-post-generator/redditomatic-reddit-post-generator.php' => [
                                        'Redditomatic Automatic Post Generator', 
                                        'https://1.envato.market/redditomatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_to_reddit($card_type_found, $title, $content, 'auto', $subreddit_to_post);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Reddit posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse Reddit posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Reddit posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'title or content parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_publish_linkedin') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['title']) && isset($arguments['content'])) 
                        {
                            if (!function_exists('is_plugin_active')) 
                            {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }
                
                            $linkedinomatic_active = is_plugin_active('linkedinomatic-linkedin-post-generator/linkedinomatic-linkedin-post-generator.php');
                            if (!$linkedinomatic_active) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Required plugin (Linkedinomatic) not activated.',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $content = $arguments['content'];
                            $title = $arguments['title'];
                
                            if (empty($content)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty content parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            if (empty($title)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty title parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $description = $arguments['description'] ?? '';
                            $post_link = $arguments['link'] ?? '';
                            $image_url = $arguments['image_url'] ?? '';
                
                            $aiomatic_Chatbot_Settings = get_option('aiomatic_Chatbot_Settings', false);
                            $selected_pages = $aiomatic_Chatbot_Settings['linkedin_selected_pages'] ?? '';
                
                            if (empty($selected_pages)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'No pages selected where to post to LinkedIn',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            require_once(dirname(__FILE__) . "/aiomatic-socials.php");
                            $card_type_found = [
                                'required_plugin' => [
                                    'linkedinomatic-linkedin-post-generator/linkedinomatic-linkedin-post-generator.php' => [
                                        'Linkedinomatic Automatic Post Generator', 
                                        'https://1.envato.market/linkedinomatic'
                                    ]
                                ]
                            ];
                            $return_me = aiomatic_post_to_linkedin($card_type_found, $content, $image_url, $title, $post_link, $description, '1', $selected_pages);
                
                            if (isset($return_me['error'])) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'LinkedIn posting failed: ' . $return_me['error'],
                                    'message' => $response['message']
                                ];
                            } 
                            elseif (empty($return_me)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to parse LinkedIn posting results',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'LinkedIn posting successful: ' . json_encode($return_me, true),
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'title or content parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                } 
                elseif ($function_name === 'aiomatic_send_email') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['subject']) && isset($arguments['content']) && isset($arguments['recipient_email'])) 
                        {
                            $subject = $arguments['subject'];
                            $content = $arguments['content'];
                            $recipient_email = $arguments['recipient_email'];
                
                            if (empty($subject) || empty($content) || empty($recipient_email)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty subject, content or recipient_email parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $headers = [
                                'From: AIomatic Plugin Chatbot <aiomatic@noreply.net>',
                                'Reply-To: noreply@aiomatic.com',
                                'X-Mailer: PHP/' . phpversion(),
                                'Content-Type: text/html',
                                'Charset: ' . get_option('blog_charset', 'UTF-8')
                            ];
                
                            $sent = wp_mail($recipient_email, $subject, $content, $headers);
                
                            if ($sent === false) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to send email to address: ' . $recipient_email,
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'OK',
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'subject, content or recipient_email parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }
                elseif ($function_name === 'aiomatic_webhook') 
                {
                    if (!empty($arguments)) 
                    {
                        $result = '';
                        if (isset($arguments['webhook_url']) && isset($arguments['method_selector'])) 
                        {
                            $webhook_url = $arguments['webhook_url'];
                            $method_selector = strtoupper($arguments['method_selector']); // Make sure the method is in uppercase
                
                            if (empty($webhook_url) || empty($method_selector)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Empty webhook_url or method_selector parameter provided',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $content_type = $arguments['content_type'] ?? '';
                            $post_template = $arguments['data'] ?? '';
                            $headers_template = $arguments['headers'] ?? '';
                
                            $urlParsed = parse_url($webhook_url, PHP_URL_HOST);
                            if (filter_var($webhook_url, FILTER_VALIDATE_URL) === false || empty($urlParsed)) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Invalid webhook_url entered: ' . $webhook_url,
                                    'message' => $response['message']
                                ];
                                continue;
                            } 
                            elseif ($content_type == 'JSON' && json_decode($post_template, true) === null) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'The JSON data must be valid: ' . $webhook_url,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $headers = [];
                            if (!empty($headers_template)) 
                            {
                                $headers_template_arr = preg_split('/\r\n|\r|\n/', trim($headers_template));
                                foreach ($headers_template_arr as $arr_fr) 
                                {
                                    if (!empty(trim($arr_fr)) && strpos($arr_fr, '=>') !== false) 
                                    {
                                        list($key, $value) = explode('=>', $arr_fr, 2);
                                        $headers[] = trim($key) . ': ' . trim($value);
                                    }
                                }
                            }
                
                            $content_params = [];
                            if (!empty($post_template)) 
                            {
                                $post_template_arr = preg_split('/\r\n|\r|\n/', trim($post_template));
                                foreach ($post_template_arr as $arr_fr) 
                                {
                                    if (!empty(trim($arr_fr)) && strpos($arr_fr, '=>') !== false) 
                                    {
                                        list($key, $value) = explode('=>', $arr_fr, 2);
                                        $content_params[trim($key)] = trim($value);
                                    }
                                }
                            }
                
                            $ch = curl_init();
                            if ($ch === false) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to initialize curl in webhook execution',
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            if (isset($aiomatic_Main_Settings['proxy_url']) && $aiomatic_Main_Settings['proxy_url'] != '' && $aiomatic_Main_Settings['proxy_url'] != 'disable') 
                            {
                                $proxy_list = explode(',', $aiomatic_Main_Settings['proxy_url']);
                                $random_proxy = trim($proxy_list[array_rand($proxy_list)]);
                                curl_setopt($ch, CURLOPT_PROXY, $random_proxy);
                                
                                if (isset($aiomatic_Main_Settings['proxy_auth']) && $aiomatic_Main_Settings['proxy_auth'] != '') 
                                {
                                    $proxy_auth_list = explode(',', $aiomatic_Main_Settings['proxy_auth']);
                                    $random_auth = trim($proxy_auth_list[array_rand($proxy_auth_list)]);
                                    if (!empty($random_auth)) 
                                    {
                                        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $random_auth);
                                    }
                                }
                            }
                
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                            if (in_array($method_selector, ['POST', 'PUT', 'DELETE'])) 
                            {
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method_selector);
                                if (!empty($content_params) && $content_type == 'form_data') 
                                {
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($content_params));
                                } 
                                elseif (!empty($post_template) && $content_type == 'JSON') 
                                {
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($content_params));
                                    $headers[] = 'Content-Type: application/json';
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                }
                            } 
                            else 
                            {
                                $query_string = http_build_query($content_params);
                                $webhook_url = $webhook_url . (strpos($webhook_url, '?') === false ? '?' : '&') . $query_string;
                            }
                
                            curl_setopt($ch, CURLOPT_URL, $webhook_url);
                
                            $response = curl_exec($ch);
                            if ($response === false) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Failed to send webhook request to ' . $webhook_url,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            $err = curl_error($ch);
                            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                
                            if ($err) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Webhook request error to URL ' . $webhook_url . ' - error: ' . $err,
                                    'message' => $response['message']
                                ];
                                continue;
                            }
                
                            if ($statusCode >= 200 && $statusCode < 300) 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Webhook URL called successfully!',
                                    'message' => $response['message']
                                ];
                            } 
                            else 
                            {
                                $toolOutputs[] = [
                                    'tool_call_id' => $zid, 
                                    'output' => 'Webhook unexpected return code to URL ' . $webhook_url . ' - return code: ' . $statusCode,
                                    'message' => $response['message']
                                ];
                            }
                        } 
                        else 
                        {
                            $toolOutputs[] = [
                                'tool_call_id' => $zid, 
                                'output' => 'webhook_url or method_selector parameter was not provided',
                                'message' => $response['message']
                            ];
                            aiomatic_log_to_file('Failed to decode Ollama function calling: ' . print_r($arguments, true));
                        }
                    } 
                    else 
                    {
                        $toolOutputs[] = [
                            'tool_call_id' => $zid, 
                            'output' => 'Arguments not set for the function call',
                            'message' => $response['message']
                        ];
                    }
                }                            
            }
        }
    }
    return $toolOutputs;
}
?>