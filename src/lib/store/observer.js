export default class Observer {
    #observers = [];
    #subject = {};

    constructor(initialVal) {
        this.#subject = initialVal;
    }

    subscribe(callback) {
        this.#observers.push(callback);
        callback(this.#subject);
    }

    #notify() {
        this.#observers.forEach((cb) => cb(this.#subject));
    }

    dispatch(newVal) {
        this.#subject = { ...this.#subject, ...newVal };
        this.#notify();
    }

    get(key) {
        return this.#subject[key];
    }
}
