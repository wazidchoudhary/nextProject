export default class Observer {
    #observers = [];
    #subject = {};

    constructor(initialVal) {
        this.#subject = initialVal;
    }

    subscribe(callback) {
        const that = this;
        var observer = {
            callback,
            key: Symbol(),
            unsubscribe: function () {
                that.#unsubscribe(this.key);
            },
        };
        observer.unsubscribe = observer.unsubscribe.bind(observer);
        this.#observers.push(observer);
        callback(this.#subject);
        return { unsubscribe: observer.unsubscribe };
    }

    #unsubscribe(key) {
        this.#observers = this.#observers.filter((observer) => {
            if (observer.key !== key) {
                return observer;
            }
        });
    }

    #notify() {
        this.#observers.forEach((observer) => observer.callback(this.#subject));
    }

    dispatch(newVal) {
        this.#subject = { ...this.#subject, ...newVal };
        this.#notify();
    }

    get(key) {
        return this.#subject[key];
    }
}
