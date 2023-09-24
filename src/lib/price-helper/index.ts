export class priceHelper {
    static lowestHighestPrice<T>(array: any) {
        if (array.length === 0) {
            return null; // Handle empty array case
        }

        let lowest = Number(array[0].price); // Initialize with the first element
        let highest = Number(array[0].price); // Initialize with the first element

        for (let i = 1; i < array.length; i++) {
            const num = Number(array[i].price);
            if (!isNaN(num)) {
                // Check if the conversion is successful
                if (num < lowest) {
                    lowest = num;
                }
                if (num > highest) {
                    highest = num;
                }
            }
        }

        return { lowest, highest };
    }

    static getPrice = (priceNew: any): string => {
        const price = typeof priceNew === 'string' ? `$${priceNew}` : `$${this.lowestHighestPrice(priceNew).lowest} - $${this.lowestHighestPrice(priceNew).highest}`;
        return price;
    };
}
