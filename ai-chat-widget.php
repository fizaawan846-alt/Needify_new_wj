
<style>
#needify-ai-button {
    position: fixed;
    right: 25px;
    bottom: 85px;
    z-index: 99999;
    background: #7c3aed;
    color: white;
    border: none;
    border-radius: 50px;
    padding: 15px 22px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 4px 15px #0003;
}

#needify-ai-box {
    display: none;
    flex-direction: column;
    position: fixed;
    right: 25px;
    bottom: 150px;
    width: 350px;
    max-width: calc(100vw - 30px);
    height: 470px;
    max-height: calc(100vh - 180px);
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 25px #0004;
    z-index: 99999;
    overflow: hidden;
    font-family: Arial, sans-serif;
}

#needify-ai-header {
    flex-shrink: 0;
    background: #7c3aed;
    color: white;
    padding: 13px;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

#needify-ai-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

#needify-ai-clear {
    background: #ffffff25;
    border: 1px solid #ffffff80;
    color: white;
    padding: 6px 9px;
    border-radius: 7px;
    font-size: 11px;
    cursor: pointer;
}

#needify-ai-close {
    background: none;
    border: none;
    color: white;
    font-size: 22px;
    cursor: pointer;
}

#needify-ai-messages {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 15px;
    background: #faf8ff;
}

.needify-ai-message {
    padding: 10px 13px;
    border-radius: 12px;
    margin-bottom: 10px;
    max-width: 85%;
    font-size: 14px;
    line-height: 1.5;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.needify-ai-user {
    background: #7c3aed;
    color: white;
    margin-left: auto;
}

.needify-ai-bot {
    background: #eee8ff;
    color: #222;
    margin-right: auto;
}

/* PRODUCT RECOMMENDATION CARDS */

.needify-ai-products {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 8px 0 15px;
    width: 100%;
}

.needify-ai-product-card {
    background: white;
    border: 1px solid #e7ddff;
    border-radius: 12px;
    padding: 10px;
    box-shadow: 0 2px 8px #0000000d;
    overflow: hidden;
}

.needify-ai-product-image {
    width: 100%;
    height: 145px;
    object-fit: contain;
    background: #f8f6fc;
    border-radius: 9px;
    display: block;
    margin-bottom: 10px;
}

.needify-ai-product-name {
    font-size: 15px;
    font-weight: bold;
    color: #29213a;
    margin-bottom: 5px;
}

.needify-ai-product-price {
    font-size: 17px;
    font-weight: bold;
    color: #7c3aed;
    margin-bottom: 7px;
}

.needify-ai-product-description {
    font-size: 12px;
    color: #666;
    line-height: 1.5;
    margin-bottom: 9px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.needify-ai-product-stock {
    display: inline-block;
    background: #ecfdf3;
    color: #168044;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.needify-ai-product-outstock {
    background: #fff0f0;
    color: #c62828;
}

#needify-ai-form {
    flex-shrink: 0;
    display: flex;
    padding: 10px;
    gap: 7px;
    border-top: 1px solid #eee;
    background: white;
}

#needify-ai-input {
    flex: 1;
    min-width: 0;
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 20px;
    outline: none;
}

#needify-ai-send {
    background: #7c3aed;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 20px;
    cursor: pointer;
}

#needify-ai-send:disabled,
#needify-ai-clear:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media(max-width: 480px) {
    #needify-ai-button {
        right: 15px;
        bottom: 85px;
    }

    #needify-ai-box {
        right: 15px;
        bottom: 150px;
        width: calc(100vw - 30px);
        max-height: calc(100vh - 180px);
    }
}
</style>

<button id="needify-ai-button" type="button">
    ✨ Needify AI
</button>

<div id="needify-ai-box">

    <div id="needify-ai-header">
        <span>Needify AI Assistant</span>

        <div id="needify-ai-header-actions">

            <button id="needify-ai-clear" type="button">
                Clear Chat
            </button>

            <button id="needify-ai-close" type="button">
                ×
            </button>

        </div>
    </div>

    <div id="needify-ai-messages">

        <div class="needify-ai-message needify-ai-bot">
            Assalam-o-Alaikum! Welcome to Needify. How can I help you?
        </div>

    </div>

    <form id="needify-ai-form">

        <input
            id="needify-ai-input"
            type="text"
            placeholder="Type your message..."
            maxlength="2000"
            autocomplete="off"
            required
        >

        <button id="needify-ai-send" type="submit">
            Send
        </button>

    </form>

</div>

<script>
(function () {

    const button = document.getElementById("needify-ai-button");
    const box = document.getElementById("needify-ai-box");
    const close = document.getElementById("needify-ai-close");
    const clear = document.getElementById("needify-ai-clear");
    const form = document.getElementById("needify-ai-form");
    const input = document.getElementById("needify-ai-input");
    const messages = document.getElementById("needify-ai-messages");
    const send = document.getElementById("needify-ai-send");

    if (
        !button || !box || !close || !clear ||
        !form || !input || !messages || !send
    ) {
        return;
    }

    let csrfToken = "";
    let busy = false;

    // ADD NORMAL CHAT MESSAGE

    function addMessage(message, type) {

        const div = document.createElement("div");

        div.className = "needify-ai-message " +
            (type === "user" ? "needify-ai-user" : "needify-ai-bot");

        div.textContent = String(message || "");

        messages.appendChild(div);

        messages.scrollTop = messages.scrollHeight;

        return div;
    }

    // NORMALIZE DATABASE IMAGE PATH

    function getProductImagePath(imagePath) {

        if (typeof imagePath !== "string" || !imagePath.trim()) {
            return "";
        }

        const path = imagePath.trim();

        if (/^https?:\/\//i.test(path)) {
            return path;
        }

        if (path.startsWith("//")) {
            return "";
        }

        if (path.startsWith("/Needify/")) {
            return path;
        }

        if (path.startsWith("/")) {
            return path;
        }

        return "/Needify/" + path.replace(/^\.?\//, "");
    }

    // DISPLAY PRODUCT RECOMMENDATION CARDS

    function showProductCards(products) {

        if (!Array.isArray(products) || products.length === 0) {
            return;
        }

        const container = document.createElement("div");

        container.className = "needify-ai-products";

        products.forEach(function (product) {

            const card = document.createElement("div");

            card.className = "needify-ai-product-card";

            // PRODUCT IMAGE

            const imagePath = getProductImagePath(product.image);

            if (imagePath) {

                const image = document.createElement("img");

                image.className = "needify-ai-product-image";

                image.src = imagePath;

                image.alt = String(product.name || "Product");

                image.loading = "lazy";

                image.onerror = function () {
                    image.style.display = "none";
                };

                card.appendChild(image);
            }

            // PRODUCT NAME

            const name = document.createElement("div");

            name.className = "needify-ai-product-name";

            name.textContent = product.name || "Unnamed Product";

            card.appendChild(name);

            // PRODUCT PRICE

            const price = document.createElement("div");

            price.className = "needify-ai-product-price";

            const numericPrice = Number(product.price);

            price.textContent = Number.isFinite(numericPrice)
                ? "Rs. " + numericPrice.toLocaleString("en-PK", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })
                : "Price unavailable";

            card.appendChild(price);

            // PRODUCT DESCRIPTION

            const description = document.createElement("div");

            description.className = "needify-ai-product-description";

            description.textContent =
                product.description || "No description available.";

            card.appendChild(description);

            // PRODUCT STOCK

            const stock = document.createElement("span");

            const stockCount = Number(product.stock);

            if (Number.isFinite(stockCount) && stockCount > 0) {

                stock.className = "needify-ai-product-stock";

                stock.textContent = "In Stock: " + stockCount;

            } else {

                stock.className =
                    "needify-ai-product-stock needify-ai-product-outstock";

                stock.textContent = "Out of Stock";
            }

            card.appendChild(stock);

            container.appendChild(card);

        });

        messages.appendChild(container);

        messages.scrollTop = messages.scrollHeight;
    }

    // WELCOME MESSAGE

    function showWelcome() {

        messages.innerHTML = "";

        addMessage(
            "Assalam-o-Alaikum! Welcome to Needify. How can I help you?",
            "bot"
        );
    }

    // GET CSRF TOKEN AND RESTORE CHAT HISTORY

    async function getToken() {

        const response = await fetch("/Needify/ai-chat.php", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        });

        const result = await response.json();

        if (
            !response.ok ||
            !result.success ||
            !result.data ||
            !result.data.csrf_token
        ) {
            throw new Error(
                "Unable to connect to AI. Please refresh the page."
            );
        }

        csrfToken = result.data.csrf_token;

        const history = result.data.history || [];

        messages.innerHTML = "";

        if (history.length === 0) {
            showWelcome();
            return;
        }

        history.forEach(function (item) {

            const text = item.parts &&
                item.parts[0] &&
                item.parts[0].text;

            if (typeof text === "string") {

                addMessage(
                    text,
                    item.role === "user" ? "user" : "bot"
                );
            }
        });
    }

    // SEND REQUEST TO PHP BACKEND

    async function sendAction(payload) {

        const response = await fetch("/Needify/ai-chat.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!response.ok || !result.success) {

            throw new Error(
                typeof result.data === "string"
                    ? result.data
                    : "Request could not be completed."
            );
        }

        return result;
    }

    // OPEN CHATBOT

    button.addEventListener("click", async function () {

        box.style.display = "flex";

        input.focus();

        if (!csrfToken) {

            try {

                await getToken();

            } catch (error) {

                addMessage(error.message, "bot");
            }
        }
    });

    // CLOSE CHATBOT

    close.addEventListener("click", function () {

        box.style.display = "none";
    });

    // CLEAR CHAT

    clear.addEventListener("click", async function () {

        if (busy) return;

        const confirmed = window.confirm(
            "Are you sure you want to clear this conversation?"
        );

        if (!confirmed) return;

        busy = true;

        clear.disabled = true;
        send.disabled = true;

        try {

            if (!csrfToken) {
                await getToken();
            }

            await sendAction({
                action: "clear",
                csrf_token: csrfToken
            });

            showWelcome();

        } catch (error) {

            addMessage(
                error.message || "Unable to clear chat.",
                "bot"
            );

        } finally {

            busy = false;

            clear.disabled = false;
            send.disabled = false;
        }
    });

    // SEND CHAT MESSAGE

    form.addEventListener("submit", async function (event) {

        event.preventDefault();

        const message = input.value.trim();

        if (!message || busy) return;

        busy = true;

        addMessage(message, "user");

        input.value = "";

        input.disabled = true;
        send.disabled = true;
        clear.disabled = true;

        const loading = addMessage("Thinking...", "bot");

        try {

            if (!csrfToken) {
                await getToken();
            }

            const result = await sendAction({

                action: "chat",

                message: message,

                csrf_token: csrfToken
            });

            loading.remove();

            // DISPLAY AI TEXT RESPONSE

            addMessage(result.data.reply, "bot");

            // DISPLAY PRODUCT CARDS

            showProductCards(result.data.recommendations);

        } catch (error) {

            loading.remove();

            addMessage(
                error.message || "Connection error. Please try again.",
                "bot"
            );

        } finally {

            busy = false;

            input.disabled = false;
            send.disabled = false;
            clear.disabled = false;

            input.focus();
        }
    });

})();
</script>
