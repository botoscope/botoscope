<?php
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('botoscope-chat', BOTOSCOPE_EXT_LINK . 'business_in_pocket/assets/css/chat.css', [], BOTOSCOPE_VERSION);

wp_enqueue_script('botoscope-chat-js', BOTOSCOPE_EXT_LINK . 'business_in_pocket/assets/js/chat.js', [], BOTOSCOPE_VERSION, true);

wp_add_inline_script('botoscope-chat-js',
        'var ajaxurl = "' . esc_url($ajaxurl) . '";' .
        'var page_url = "' . esc_url(str_replace('http://', 'https://', get_site_url(null, 'botoscope-chat'))) . '";' .
        'var botoscope_ticket_id = ' . intval($ticket_id) . ';' .
        'var botoscope_nonce = "' . esc_attr($nonce) . '";',
        'before'
);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title><?php esc_html_e('Botoscope Support Chat', 'botoscope') ?></title>
        <?php wp_print_styles('botoscope-chat'); ?>
    </head>
    <body>
        <?php if ($non_answered_tickets): ?>
            <div class="dropdown-container">
                <div class="dropdown-header" onclick="toggleDropdown()">👥 <?php
                    printf(
                            /* translators: %d: number of clients */esc_html__('Clients waiting for reply (%d)', 'botoscope'),
                            count($non_answered_tickets)
                    )
                    ?></div>
                <div class="dropdown-content" id="dropdownContent">

                    <?php foreach ($non_answered_tickets as $t) : if (intval($ticket_id) === intval($t['id'])) continue; ?>
                        <div class="client-entry" onclick="toggle_ticket(<?php echo intval($t['id']) ?>, '<?php echo esc_attr($t['hash_key']) ?>')">
                            <?php
                            printf(
                                    '🕓 ' . /* translators: %d: ticket number */esc_html__('Ticket #%d — waiting for reply', 'botoscope'),
                                    intval($t['id'])
                            )
                            ?>
                        </div>
                    <?php endforeach; ?>

                </div>
                <div style="position: absolute; right: 9px; top: 12px;">
                    #<?php echo intval($ticket_id) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="chat-container" id="chat">

            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $m) : ?>

                    <?php $date = BOTOSCOPE_HELPER::format_time($m['time']); ?>

                    <?php if ($m['message_type'] === 'question'): ?>

                        <div class="message customer">
                            <?php echo nl2br(esc_html($m['content'])) ?>
                            <div class="meta"><?php esc_html_e('Customer', 'botoscope') ?> · <?php echo esc_html($date) ?></div>
                        </div>


                    <?php else: ?>

                        <div class="message you">
                            <?php echo nl2br(esc_html($m['content'])) ?>
                            <div class="meta"><?php esc_html_e('You', 'botoscope') ?> · <?php echo esc_html($date) ?></div>
                        </div>

                    <?php endif; ?>


                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="input-area">
            <textarea id="chatInput" placeholder="<?php esc_html_e('Write a message...', 'botoscope') ?>" oninput="autoResize(this)" rows="1"></textarea>
            <button class="send-button" onclick="sendMessage()">
                <svg viewBox="0 0 24 24">
                <path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/>
                </svg>
            </button>
        </div>

        <?php wp_print_scripts('botoscope-chat-js'); ?>
    </body>
</html>
