import React, { useEffect } from 'react';
import Button from "../components/Button";

export default function ReactExample({ csrf, translations }) {
  console.log(csrf);
  console.log(translations);

  const historyBack = () => {
    history.back();
  }
  const exampleFetch = async () => {
    let input = {}
    input = {
      email: "example@example.com",
      password: "Mypassword.123",
      _csrf: csrf
    }
    const data = JSON.stringify(input)
    const r = await fetch("login/",
      {
        method: "POST",
        body: data,
        headers: {
          "Content-Type": "application/json"
        }
      });
    if (!r.ok) {
      const errors = await r.json();
      console.log(`Try ommenting the var input`);
      console.log(errors);
      return;
    }
    const response = await r.json();
    console.log(response)
  }
  useEffect(() => {
    exampleFetch();
  }, []);
  return (
    <div className="flex flex-col min-h-screen justify-center items-center">
      <article className="rounded-xl p-4 bg-white shadow-md rounded-lg  px-8 py-8 mx-auto">
        <h2 className="text-xl font-bold text-gray-800">React full page</h2>
        <p className="mt-2 text-gray-500 text-sm">This is a React page rendered inside a Lila function Render.</p>

        <div className='flex justify-center flex-col mt-4 gap-4'>
          <Button onClick={exampleFetch}  >
            Send fetch example to /login
          </Button>

          <Button onClick={historyBack} className="rounded-xl mt-4 bg-gray-100 text-gray-500 font-semibold px-4 py-2 rounded hover:bg-gray-200">
            Back to index
          </Button>
        </div>


      </article>
    </div>
  );
}