
const express = require("express");

const app = express();
const PORT = 3000;

app.use(express.json());

// Express REST API endpoint
app.get("/api/message", (req, res) => {
    res.json({
        success: true,
        message: "Hello! PHP successfully called the Express REST API.",
        application: "Shopping Cart",
        technology: "Node.js and Express"
    });
});


app.get("/api/students", async (req, res) => {
    try {
        const response = await fetch(
            "http://localhost/shopping_cart/api/php_api.php"
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            return res.status(502).json({
                success: false,
                message: "PHP API request failed."
            });
        }

        res.json({
            success: true,
            message: "Express successfully called the PHP REST API.",
            result: data
        });
    } catch (error) {
        console.error("PHP API error:", error.message);

        res.status(502).json({
            success: false,
            message: "Unable to connect to the PHP API."
        });
    }
});

app.listen(PORT, () => {
    console.log(`Express server running at http://localhost:${PORT}`);
});
