const { default: Observer } = require('./observer');

const store = new Observer({
    cart: [],

    allProducts: [],
});

export default store;
