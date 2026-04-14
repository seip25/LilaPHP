import React, { useState } from 'react';
import Button from './Button';

export default function ButtonPage({ label }) {
  const [newLabel, setNewLabel] = useState(label);
  const handleClick = () => {
    const newCount = parseInt(localStorage.getItem("cartCount")) ? parseInt(localStorage.getItem("cartCount")) : 0;
    localStorage.setItem("cartCount", newCount + 1);
    console.log(newCount + 1);
    renderReactComponent("Header");
    setNewLabel(`Random text ${Math.random()}`)

  }

  return (
    <Button onClick={handleClick}  >
      {newLabel}
    </Button>
  );
}