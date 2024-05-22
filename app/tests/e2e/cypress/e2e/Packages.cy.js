describe('Packages', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/packages$/ }).as('api-packages-query')
  })

  it('shows title', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    cy.contains('h1', 'Package statistics')
  })

  it('adds and removes packages for comparison', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    cy.contains('div', 'No packages selected.' +
      ' Use the search below to add packages and allow the generation of a comparison' +
      ' graph over time.')

    /* add two packages and assert they are shown */
    const firstPackageName = cy.get('table').find('td').first().innerText
    cy.get('[data-test=toggle-pkg-in-comparison]').first().invoke('click')
    const firstBadgeName = cy.get('span[class="pkg-badge-content"]').first().innerText
    cy.expect(firstPackageName).equals(firstBadgeName)

    const lastPackageName = cy.get('table').find('td').last().innerText
    cy.get('[data-test=toggle-pkg-in-comparison]').last().invoke('click')
    const lastBadgeName = cy.get('span[class="pkg-badge-content"]').last().innerText
    cy.expect(lastPackageName).equals(lastBadgeName)

    cy.get('span[class="pkg-badge-content"]').should('have.length', 2)

    /* test removal via CTA in table */
    cy.get('[data-test=toggle-pkg-in-comparison]').last().invoke('click')
    cy.get('span[class="pkg-badge-content"]').should('have.length', 1)

    /* add back the last visible package to assert removal via the X on the badge */
    cy.get('[data-test=toggle-pkg-in-comparison]').last().invoke('click')
    cy.get('.btn.btn-secondary.pkg-badge-button').last().invoke('click')
    cy.get('span[class="pkg-badge-content"]').should('have.length', 1)
  })

  it('shows comparison graph', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    cy.get('[data-test=toggle-pkg-in-comparison]').invoke('click')

    /* cypress cannot open link in new tabs, so we open in the current tab to see the chart */
    cy.get('[data-test-name=comparison-graph-link').invoke('removeAttr', 'target').click()
    cy.assertCanvasIsNotEmpty('[data-test=package-chart][data-test-rendered=true][style]')
  })

  it('searches', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    cy.get('input[type=search]').type('firefox')
    cy.location().should((loc) => {
      expect(loc.search).to.eq('?query=firefox')
    })
    cy.get('[data-test-name=firefox] [role=progressbar][aria-valuenow*="."]').invoke('text').should('match', /^\d+/)
    cy.contains('[role=alert]', 'packages found')
    cy.contains('a', 'firefox')
  })

  it('handles invalid input', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    cy.get('input[type=search]').type('{bar}', { parseSpecialCharSequences: false })
    cy.contains('[role=alert]', 'Bad Request')
  })

  it('loads additional packages when scrolling', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    const packageLimit = 60

    cy.get('table').find('tr').should('have.length', packageLimit + 1)
    cy.scrollTo('bottom')
    cy.wait('@api-packages-query')

    cy.get('table').find('tr').should('have.length', packageLimit * 2 + 1)
  })

  it('searches by url', () => {
    cy.visit('/packages?query=firefox')
    cy.wait('@api-packages-query')

    cy.get('[role=progressbar]').should('be.visible')
    cy.contains('[role=alert]', 'packages found')
    cy.contains('a', 'firefox')
  })
})
