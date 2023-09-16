const { default: Observer } = require("./observer");

const store = new Observer({
    cart: [
        {id: 1, productName: 'Ice cream', quantity: 2, price: 10},
        {id: 2, productName: 'Chicken', quantity: 1, price: 100},
        {id: 3, productName: 'Macbook Pro', quantity: 1, price: 120000}
    ],

    allProducts: []
});


export default store;