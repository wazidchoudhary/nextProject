export default class datePipe {
     monthNames = [
        "Jan", "Feb", "Mar", "Apr", "May", "Jun", 
        "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
    ];

    static dateConvert = (inputDate) => {
        const monthNames = [
            "Jan", "Feb", "Mar", "Apr", "May", "Jun", 
            "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
        ];
        const dateParts = inputDate.split('-');
        if (dateParts.length !== 3 || dateParts[1] < 1 || dateParts[1] > 12) {
            console.error('Invalid date format');
            return;
        }

        const day = dateParts[2];
        const month = monthNames[9]; // because months are 0-indexed
        const year = dateParts[1];

        const newFormat = `${day}-${month}-${year}`;
        return newFormat
    };

    static get15DaysLater = (inputDate) => {
        monthNames = [
            "Jan", "Feb", "Mar", "Apr", "May", "Jun", 
            "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
        ];
        // Parse the input date
        const parts = inputDate.split('-');
        const day = parseInt(parts[2], 10);
        const month = parseInt(parts[1], 10) - 1; // months are 0-indexed in Date object
        const year = parseInt(parts[0], 10);
    
        // Create a Date object from the parsed input
        const dateObj = new Date(year, month, day);
    
        // Add 15 days to the date
        dateObj.setDate(dateObj.getDate() + 15);
    
        // Format the new date into 'dd-mon-yyyy' format
        const newDay = String(dateObj.getDate()).padStart(2, '0');
        const newMonth = monthNames[dateObj.getMonth()]; 
        const newYear = dateObj.getFullYear();
    
        return `${newDay}-${newMonth}-${newYear}`;
    }
}