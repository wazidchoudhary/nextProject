export default class StrUtils {
   static normalToSnake = (str) => {
    return str.replace(/ /g,"-").toLowerCase();
   }

   static snakeToNormal = (str) =>{
      return str.replace(/-/g, ' ').toUpperCase();
   }
}


