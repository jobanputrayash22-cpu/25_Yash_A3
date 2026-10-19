
async function loadProducts(categoryId = "", page = 1) {
    const container = document.getElementById("products");
    const pagination = document.getElementById("pagination");
    const status = document.getElementById("status");

    status.textContent = "Loading products...";
    container.replaceChildren();
    pagination.replaceChildren();

    const params = new URLSearchParams({ page });

    if (categoryId !== "") {
        params.set("category_id", categoryId);
    }

    try {
        const response = await fetch(
            `api/products.php?${params.toString()}`
        );

        if (!response.ok) {
            throw new Error("Could not load products.");
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        if (data.products.length === 0) {
            status.textContent = "No products found.";
            return;
        }

        status.textContent =
            `Showing ${data.products.length} products per page. ` +
            `Total products: ${data.totalProducts}`;

        data.products.forEach(product => {
            const card = document.createElement("article");
            card.className = "product-card";

            if (product.image) {
                const img = document.createElement("img");
                img.src = "uploads/" +
                    encodeURIComponent(product.image);
                img.alt = product.name;
                img.loading = "lazy";
                card.appendChild(img);
            }

            const details = document.createElement("div");
            details.className = "product-info";

            const title = document.createElement("h3");
            title.textContent = product.name;

            const description = document.createElement("p");
            description.textContent = product.description || "";

            const price = document.createElement("p");
            price.className = "price";
            price.textContent =
                "₹" + Number(product.price).toFixed(2);

            details.append(title, description, price);
            card.appendChild(details);
            container.appendChild(card);
        });

        // Create pagination buttons
        for (let p = 1; p <= data.totalPages; p++) {
            const button = document.createElement("button");
            button.textContent = p;
            button.className =
                p === data.currentPage ? "active-page" : "";

            button.addEventListener("click", () => {
                loadProducts(categoryId, p);
            });

            pagination.appendChild(button);
        }

    } catch (error) {
        status.textContent = error.message;
    }
}

// Read category ID from the page URL
const params = new URLSearchParams(window.location.search);
const categoryId = params.get("category_id") || "";

loadProducts(categoryId);
