import React, { useState } from 'react';

export default function Header({ user, cartCount }) {
  const [count, setCount] = useState(cartCount || 0);

  return (
    <header className="p-4 bg-white shadow-md rounded-lg flex justify-between items-center">
      <h1 className="text-xl font-bold text-gray-800">Hello, {user?.name || 'Guest'}!</h1>
      <button 
        onClick={() => setCount(c => c + 1)}
        className="px-4 py-2 bg-gray-900 text-white rounded"
      >
        Cart: {count}
      </button>
    </header>
  );
}