
<?php

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/gemini_client.php';

// LOGIN CHECK
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF TOKEN
if (empty($_SESSION['ai_chat_csrf'])) {
    $_SESSION['ai_chat_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/includes/header.php';

?>

<section class="section">
    <div class="container">

        <h2>Needify AI Shopping Assistant</h2>

        <div
            id="chatBox"
            style="
                max-width:800px;
                margin:auto;
                border:1px solid var(--border);
                border-radius:8px;
                padding:16px;
                height:400px;
                overflow-y:auto;
                background:var(--card-bg);
            "
        >
            <div id="messages"></div>
        </div>

        <form
            id="chatForm"
            style="
                margin-top:12px;
                max-width:800px;
                display:flex;
                gap:8px;
            "
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['ai_chat_csrf']) ?>"
            >

            <input
                type="text"
                name="prompt"
                id="prompt"
                placeholder="Ask something about products or needs..."
                required
                maxlength="2000"
                style="flex:1; padding:8px;"
            >

            <button
                type="submit"
                id="sendButton"
                class="btn btn-primary"
            >
                Send
            </button>

        </form>

    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('chatForm');
    const messagesDiv = document.getElementById('messages');
    const chatBox = document.getElementById('chatBox');
    const promptInput = document.getElementById('prompt');
    const sendButton = document.getElementById('sendButton');

    // SAFELY DISPLAY CHAT MESSAGES
    function addMessage(text, author) {

        const div = document.createElement('div');

        div.style.marginBottom = '12px';
        div.style.whiteSpace = 'pre-wrap';
        div.style.overflowWrap = 'anywhere';

        const strong = document.createElement('strong');
        strong.textContent = author + ': ';

        const messageText = document.createElement('span');
        messageText.textContent = String(text);

        div.appendChild(strong);
        div.appendChild(messageText);

        messagesDiv.appendChild(div);

        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // HANDLE CHAT SUBMISSION
    form.addEventListener('submit', async function (event) {

        event.preventDefault();

        const prompt = promptInput.value.trim();

        if (!prompt) {
            return;
        }

        addMessage(prompt, 'You');

        const formData = new FormData(form);

        promptInput.value = '';
        sendButton.disabled = true;
        sendButton.textContent = 'Please wait...';

        try {

            const response = await fetch('ai_chat_endpoint.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {

                addMessage(data.reply, 'AI');

            } else {

                addMessage(
                    data.error || 'Something went wrong. Please try again.',
                    'AI'
                );

            }

        } catch (error) {

            addMessage(
                'Unable to connect to the AI service. Please try again.',
                'AI'
            );

        } finally {

            sendButton.disabled = false;
            sendButton.textContent = 'Send';
            promptInput.focus();

        }

    });

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
