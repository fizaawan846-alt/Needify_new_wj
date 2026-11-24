
<?php
// =====================================================
// NEEDIFY - GENERAL CONTACT US PAGE
// =====================================================

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Contact Us | Needify";

require_once __DIR__ . '/includes/header.php';
?>

<style>
.contact-page {
    max-width: 1100px;
    margin: 50px auto;
    padding: 20px;
}

.contact-heading {
    text-align: center;
    margin-bottom: 35px;
}

.contact-heading h1 {
    font-size: 34px;
    color: #222;
    margin-bottom: 10px;
}

.contact-heading p {
    color: #777;
    font-size: 16px;
}

.contact-container {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 30px;
}

.contact-info,
.contact-form {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 3px 18px rgba(0,0,0,0.08);
}

.contact-info h2,
.contact-form h2 {
    margin-bottom: 20px;
    color: #222;
}

.contact-info p {
    color: #666;
    line-height: 1.7;
    margin-bottom: 20px;
}

.contact-detail {
    margin: 22px 0;
    color: #444;
    line-height: 1.8;
}

.contact-detail i {
    color: #e67e22;
    margin-right: 10px;
}

.contact-form label {
    display: block;
    margin-bottom: 7px;
    font-weight: 600;
    color: #333;
}

.contact-form input,
.contact-form textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 18px;
    border: 1px solid #ddd;
    border-radius: 7px;
    font-size: 15px;
    box-sizing: border-box;
    outline: none;
}

.contact-form input:focus,
.contact-form textarea:focus {
    border-color: #e67e22;
}

.contact-form textarea {
    min-height: 140px;
    resize: vertical;
}

.contact-submit {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 7px;
    background: #e67e22;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.contact-submit:hover {
    background: #cf6d17;
}

@media (max-width: 768px) {
    .contact-container {
        grid-template-columns: 1fr;
    }

    .contact-heading h1 {
        font-size: 27px;
    }
}
</style>

<div class="contact-page">

    <div class="contact-heading">
        <h1>Contact Us</h1>
        <p>Have a question or need help? We would love to hear from you.</p>
    </div>

    <div class="contact-container">

        <!-- CONTACT INFORMATION -->
        <div class="contact-info">

            <h2>Get in Touch</h2>

            <p>
                Thank you for visiting Needify!
                If you have any questions, suggestions,
                or need assistance, feel free to contact us.
            </p>

            <div class="contact-detail">
                <i class="fa-solid fa-envelope"></i>
                <strong>Email:</strong><br>
                Contact us through our message form.
            </div>

            <div class="contact-detail">
                <i class="fa-brands fa-whatsapp"></i>
                <strong>WhatsApp:</strong><br>
                +92 335 0093455
            </div>

            <div class="contact-detail">
                <i class="fa-solid fa-location-dot"></i>
                <strong>Location:</strong><br>
                University of Sargodha, Pakistan
            </div>

        </div>

        <!-- CONTACT FORM -->
        <div class="contact-form">

            <h2>Send Us a Message</h2>

            <form id="contactForm">

                <label for="contactName">Your Name</label>
                <input
                    type="text"
                    id="contactName"
                    placeholder="Enter your name"
                    required
                >

                <label for="contactEmail">Your Email</label>
                <input
                    type="email"
                    id="contactEmail"
                    placeholder="Enter your email"
                    required
                >

                <label for="contactSubject">Subject</label>
                <input
                    type="text"
                    id="contactSubject"
                    placeholder="Enter message subject"
                    required
                >

                <label for="contactMessage">Message</label>
                <textarea
                    id="contactMessage"
                    placeholder="Write your message here..."
                    required
                ></textarea>

                <button type="submit" class="contact-submit">
                    <i class="fa-brands fa-whatsapp"></i>
                    Send Message
                </button>

            </form>

        </div>

    </div>
</div>

<script>
document.getElementById("contactForm").addEventListener("submit", function(e) {

    e.preventDefault();

    const name = document.getElementById("contactName").value.trim();
    const email = document.getElementById("contactEmail").value.trim();
    const subject = document.getElementById("contactSubject").value.trim();
    const message = document.getElementById("contactMessage").value.trim();

    if (!name || !email || !subject || !message) {
        alert("Please fill in all fields.");
        return;
    }

    const whatsappMessage =
        "Hello Needify Team!\n\n" +
        "Name: " + name + "\n" +
        "Email: " + email + "\n" +
        "Subject: " + subject + "\n\n" +
        "Message:\n" + message;

    const whatsappURL =
        "https://wa.me/923350093455?text=" +
        encodeURIComponent(whatsappMessage);

    window.open(whatsappURL, "_blank");

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
