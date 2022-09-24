Cypress.Commands.add('assertCanvasIsNotEmpty', (canvas) => {
  cy.get(canvas).then(element => {
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(500)

    const canvas = element[0]

    assert.isAtLeast(canvas.width, 100)
    assert.isAtLeast(canvas.height, 100)

    const blank = document.createElement('canvas')
    blank.width = canvas.width
    blank.height = canvas.height

    assert.notStrictEqual(canvas.toDataURL(), blank.toDataURL())
  })
})
