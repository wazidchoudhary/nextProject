const { default: Observer } = require('./observer');

const store = new Observer({
    cart: [],
    allProducts: [],
    wishList: [],
});

export default store;
