import { useState, useEffect } from 'react'

interface Column {
  label: string
  type: string
}

interface Cell {
  v: string | number
}

interface Row {
  c: Cell[]
}

interface ApiResponse {
  cols: Column[]
  rows: Row[]
}

function App() {
  const [data, setData] = useState<ApiResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    fetch('/index.php?json=1&start=0')
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch data')
        }
        return response.json()
      })
      .then((json: ApiResponse) => {
        setData(json)
        setLoading(false)
      })
      .catch(err => {
        setError(err.message)
        setLoading(false)
      })
  }, [])

  if (loading) {
    return (
      <div className="container">
        <h1>CUETools DB</h1>
        <p className="loading">Loading...</p>
      </div>
    )
  }

  if (error) {
    return (
      <div className="container">
        <h1>CUETools DB</h1>
        <p className="error">Error: {error}</p>
      </div>
    )
  }

  if (!data) {
    return (
      <div className="container">
        <h1>CUETools DB</h1>
        <p>No data available</p>
      </div>
    )
  }

  // Columns to hide
  const hiddenColumns = ['CRC32', 'TOC', 'Track CRCs']
  const visibleColIndices = data.cols
    .map((col, index) => ({ col, index }))
    .filter(({ col }) => !hiddenColumns.includes(col.label))
    .map(({ index }) => index)

  return (
    <div className="container">
      <h1>CUETools DB</h1>
      <div className="table-wrapper">
        <table>
          <thead>
            <tr>
              {visibleColIndices.map((colIndex) => (
                <th key={colIndex}>{data.cols[colIndex].label}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row, rowIndex) => (
              <tr key={rowIndex}>
                {visibleColIndices.map((colIndex) => (
                  <td key={colIndex}>{row.c[colIndex].v}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export default App
