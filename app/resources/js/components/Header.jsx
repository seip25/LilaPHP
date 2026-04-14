import React, { useEffect, useState } from 'react';

export default function Header({ user, cartCount }) {
  const [count, setCount] = useState(0);

  useEffect(() => {
    const newCount = localStorage.getItem("cartCount") ? localStorage.getItem("cartCount") : cartCount;
    setCount(newCount);
  }, []);

  const addToCart = () => {
    setCount(count + 1);
    localStorage.setItem("cartCount", count + 1);
  };

  return (
    <header className="p-4 bg-white shadow-md rounded-lg flex justify-between items-center">
      <h1 className="text-xl font-bold text-gray-800">Hello, {user?.name || 'Guest'}!</h1>
      <button
        onClick={() => addToCart()}
        className="px-4 py-2 bg-gray-900 text-white rounded"
      >
        Cart: {count}
      </button>
    </header>
  );
}