import React, { useState } from 'react';
import Button from '../components/Button';

export default function ButtonPage({ label }) {
  const [newLabel, setNewLabel] = useState(label);
  const handleClick = () => setNewLabel(`Random text ${Math.random()}`)

  return (
    <Button onClick={handleClick}  >
      {newLabel}
    </Button>
  );
}