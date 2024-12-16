document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');

    searchButton.addEventListener('click', function() {
        searchProducts();
    });

    // Juga memungkinkan search dengan menekan Enter
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchProducts();
        }
    });
});

// Fungsi untuk search products
function searchProducts() {
    const searchValue = document.getElementById('searchInput').value;
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('search', searchValue);
    window.location.href = currentUrl.toString();
}

const { MongoClient } = require('mongodb');

async function updateDocuments() {
    const uri = "mongodb://localhost:27017"; // Ganti dengan URI MongoDB Anda
    const client = new MongoClient(uri);

    try {
        await client.connect();
        const database = client.db('1399meubel'); // Ganti dengan nama database Anda
        const collection = database.collection('products'); // Ganti dengan nama koleksi Anda

        // Update semua dokumen untuk menambahkan field img_url
        const result = await collection.updateMany(
            {}, // Filter: kosong berarti semua dokumen
            { $set: { img_url: "http://example.com/assets/images/products/default.jpg" } } // Menambahkan field img_url
        );

        console.log(`${result.modifiedCount} dokumen telah diperbarui.`);
    } finally {
        await client.close();
    }
}

updateDocuments().catch(console.error);