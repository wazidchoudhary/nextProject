// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
import { getDatabase } from "firebase/database";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyDWSAxHuX67UZ2cdum-Hf8w1ORY5oQBQP0",
  authDomain: "asinternational-35959.firebaseapp.com",
  databaseURL: "https://asinternational-35959-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId: "asinternational-35959",
  storageBucket: "asinternational-35959.appspot.com",
  messagingSenderId: "593646255077",
  appId: "1:593646255077:web:b75c6ed7b26ac3b0424d0c",
  measurementId: "G-2ZV1WG48D4"
};

// Initialize Firebase
export const app = initializeApp(firebaseConfig);
export const database = getDatabase();