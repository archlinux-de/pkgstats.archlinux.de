Cypress.Commands.add('assertCanvasIsNotEmpty', (canvas) => {
  cy.get(canvas).then(element => {
    const canvas = element[0]

    assert.isAtLeast(canvas.width, 100)
    assert.isAtLeast(canvas.height, 100)

    const blank = document.createElement('canvas')
    blank.width = canvas.width
    blank.height = canvas.height

    assert.notStrictEqual(canvas.toDataURL(), blank.toDataURL())
  })
})
