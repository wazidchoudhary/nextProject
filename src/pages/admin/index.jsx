import React, { useState } from 'react'
export default function () {

    const [username,setUsername] = useState('')
    const [password,setPassword] = useState('')
    const user = 'asinternational'
    const pass='Shakulat@123'
    const handleSubmit = (e) =>{
        e.preventDefault()
        if(username == user && password == pass){
            window.location.href = 'https://asinternational.000webhostapp.com/index.php';
        }
    }
  return (
    <div className="auth-container">
    <form onSubmit={handleSubmit} className="login-form">
        <div className="input-group-admin">
            <label htmlFor="username">Username</label>
            <input
                type="text"
                id="username"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
            />
        </div>
        <div className="input-group-admin">
            <label htmlFor="password">Password</label>
            <input
                type="password"
                id="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
            />
        </div>
        <button type="submit" className="login-button-admin">Login</button>
        <div className="options-admin">
            <a href="/forgot-password">Forgot Password?</a>
            <a href="/signup">Sign Up</a>
        </div>
    </form>
</div>
  )
}



